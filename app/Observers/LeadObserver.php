<?php

namespace App\Observers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\ScoreLead;
use App\Jobs\SendTelegramLeadAlertJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Jobs\SyncLeadToWadeskJob;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use App\Support\Ai;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues AI lead scoring whenever a lead is created, or updated in a way that
 * could change its score. Covers every creation path (back-office form, public
 * lead-capture API, future Livewire) because it hooks the model, not a
 * controller. Gated by Ai::enabled() so it is a true no-op when AI is off.
 *
 * Also auto-assigns unowned leads on create, independent of AI_ENABLED — this
 * is routing, not an AI feature, and leads shouldn't sit unowned just because
 * AI is off. Checks admin-configured LeadAssignmentRule rows first (campaign
 * match, then service match), falling back to the least-loaded active Sales
 * user when no rule applies.
 */
class LeadObserver
{
    /** Fields that materially affect the score; an update touching none is ignored. */
    private const SCORING_FIELDS = ['name', 'company', 'email', 'phone', 'source', 'service_id', 'estimated_value'];

    public function created(Lead $lead): void
    {
        $this->autoAssign($lead);
        $this->queueScore($lead);
        $this->notifyNewLead($lead);
        $this->sendVisibilityAuditInviteIfEligible($lead);

        // autoAssign()'s own save() above already fires a nested updated()
        // call when it finds an assignee, which handles the wadesk.in sync
        // below via the owner_id check — only handle the leftover case here
        // (no active Sales user to assign, owner_id still null) so an
        // unowned lead still gets staged, without double-dispatching.
        if ($lead->owner_id === null) {
            $this->syncToWadesk($lead);
        }
    }

    public function updated(Lead $lead): void
    {
        // The job writes ai_* columns via saveQuietly() (no event), so they never
        // reach here — only genuine field edits trigger a re-score.
        if ($lead->wasChanged(self::SCORING_FIELDS)) {
            $this->queueScore($lead);
        }

        // Keep wadesk.in's conversation assignment in sync with reassignment
        // (covers both autoAssign()'s initial nested save and any later
        // manual reassignment).
        if ($lead->wasChanged('owner_id')) {
            $this->syncToWadesk($lead);
        }

        // Covers the real race condition where Meta's own auto-sent WhatsApp
        // message reaches the CRM before the Lead Ads webhook does: the Lead
        // is created first via WhatsApp (source=whatsapp, no meta_leadgen_id,
        // often no service_id), then ImportMetaLead::attachToExistingLead()
        // backfills meta_leadgen_id/service_id onto it a moment later via a
        // plain update() — which never reaches created() again. Without this,
        // that lead is invisible to the invite despite genuinely having
        // submitted the Meta form.
        if ($lead->wasChanged(['meta_leadgen_id', 'service_id'])) {
            $this->sendVisibilityAuditInviteIfEligible($lead);
        }
    }

    /**
     * Assign the lead to a rule-matched Sales user if one applies, otherwise
     * to whichever active Sales user currently owns the fewest open leads —
     * so new leads stop sitting at owner_id=null. Ties on the round-robin
     * fallback break on user id for deterministic behaviour. A normal
     * (non-quiet) save is used deliberately — who a lead got assigned to is
     * a real business fact worth an activity-log entry, unlike the AI columns.
     */
    private function autoAssign(Lead $lead): void
    {
        if ($lead->owner_id !== null) {
            return;
        }

        $assignee = $this->resolveRuleAssignee($lead) ?? $this->resolveLeastLoadedSales();

        if ($assignee === null) {
            return;
        }

        $lead->owner_id = $assignee->id;
        $lead->save();
    }

    /**
     * Checks admin-configured LeadAssignmentRule rows, campaign match taking
     * priority over service match (see CLAUDE.md's decisions log). Re-checks
     * the rule's target is still an active Sales user at match time — a rule
     * created against a user who was later deactivated or role-changed must
     * fall through to the round-robin, not silently assign to someone
     * ineligible.
     */
    private function resolveRuleAssignee(Lead $lead): ?User
    {
        if ($lead->utm_campaign !== null) {
            $assignee = $this->matchRule(LeadAssignmentRule::active()->where('utm_campaign', $lead->utm_campaign));

            if ($assignee !== null) {
                return $assignee;
            }
        }

        if ($lead->service_id !== null) {
            $assignee = $this->matchRule(LeadAssignmentRule::active()->where('service_id', $lead->service_id));

            if ($assignee !== null) {
                return $assignee;
            }
        }

        return null;
    }

    /**
     * @param  Builder<LeadAssignmentRule>  $query
     */
    private function matchRule($query): ?User
    {
        $rule = $query->with('assignedUser')->first();

        if ($rule === null || $rule->assignedUser === null) {
            return null;
        }

        $user = $rule->assignedUser;

        return $user->is_active && $user->role === UserRole::Sales ? $user : null;
    }

    private function resolveLeastLoadedSales(): ?User
    {
        return User::where('is_active', true)
            ->where('role', UserRole::Sales->value)
            ->withCount(['leads as open_leads_count' => fn ($query) => $query->whereIn('status', LeadStatus::openValues())])
            ->orderBy('open_leads_count')
            ->orderBy('id')
            ->first();
    }

    private function queueScore(Lead $lead): void
    {
        if (Ai::enabled()) {
            ScoreLead::dispatch($lead->id);
        }
    }

    private function syncToWadesk(Lead $lead): void
    {
        SyncLeadToWadeskJob::dispatch($lead->id);
    }

    /**
     * Meta's native Lead Ads form never sends the submitter anywhere — this
     * is what actually shows them the Visibility Audit offer for the first
     * time. Deliberately scoped to leads that genuinely submitted a Meta
     * lead form, tagged the GMB service specifically (not every Meta lead —
     * a Website Design or SEO inquiry has nothing to do with this offer),
     * confirmed with the owner.
     *
     * Gated on meta_leadgen_id, NOT source === MetaAds — a real production
     * lead (id 225) surfaced why: Meta's own auto-sent WhatsApp message
     * often reaches the CRM before the Lead Ads webhook does, so the Lead
     * briefly exists with source=whatsapp while meta_leadgen_id/service_id
     * land moments later via attachToExistingLead() (which now also
     * corrects source back to Meta Ads there — see its own docblock).
     * meta_leadgen_id is set unconditionally on both paths regardless of
     * source, so it remains the reliable "did this really submit the Meta
     * form" signal to gate the invite on.
     */
    private function sendVisibilityAuditInviteIfEligible(Lead $lead): void
    {
        if ($lead->meta_leadgen_id === null || $lead->service_id === null) {
            return;
        }

        $gmbServiceId = Service::where('name', 'GMB')->value('id');

        if ($lead->service_id === $gmbServiceId) {
            SendVisibilityAuditFirstInviteJob::dispatch($lead->id);
        }
    }

    private function notifyNewLead(Lead $lead): void
    {
        $notification = new NewLeadNotification($lead);

        if ($lead->owner_id) {
            User::find($lead->owner_id)?->notify($notification);
        } else {
            User::where('is_active', true)
                ->withAnyRole(UserRole::Sales)
                ->get()
                ->each(fn (User $u) => $u->notify($notification));
        }

        SendTelegramLeadAlertJob::dispatch($lead->id);
    }
}
