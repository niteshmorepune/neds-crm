<?php

namespace App\Observers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use App\Support\Ai;

/**
 * Queues AI lead scoring whenever a lead is created, or updated in a way that
 * could change its score. Covers every creation path (back-office form, public
 * lead-capture API, future Livewire) because it hooks the model, not a
 * controller. Gated by Ai::enabled() so it is a true no-op when AI is off.
 *
 * Also auto-assigns unowned leads to the least-loaded active Sales user, on
 * create, independent of AI_ENABLED — this is routing, not an AI feature, and
 * leads shouldn't sit unowned just because AI is off.
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
    }

    public function updated(Lead $lead): void
    {
        // The job writes ai_* columns via saveQuietly() (no event), so they never
        // reach here — only genuine field edits trigger a re-score.
        if ($lead->wasChanged(self::SCORING_FIELDS)) {
            $this->queueScore($lead);
        }
    }

    /**
     * Assign the lead to whichever active Sales user currently owns the
     * fewest open leads, so new leads stop sitting at owner_id=null. Ties
     * break on user id for deterministic behaviour. A normal (non-quiet)
     * save is used deliberately — who a lead got assigned to is a real
     * business fact worth an activity-log entry, unlike the AI columns.
     */
    private function autoAssign(Lead $lead): void
    {
        if ($lead->owner_id !== null) {
            return;
        }

        $openStatuses = array_map(
            fn (LeadStatus $status) => $status->value,
            array_values(array_filter(LeadStatus::cases(), fn (LeadStatus $status) => $status->isOpen())),
        );

        $assignee = User::where('is_active', true)
            ->where('role', UserRole::Sales->value)
            ->withCount(['leads as open_leads_count' => fn ($query) => $query->whereIn('status', $openStatuses)])
            ->orderBy('open_leads_count')
            ->orderBy('id')
            ->first();

        if ($assignee === null) {
            return;
        }

        $lead->owner_id = $assignee->id;
        $lead->save();
    }

    private function queueScore(Lead $lead): void
    {
        if (Ai::enabled()) {
            ScoreLead::dispatch($lead->id);
        }
    }

    private function notifyNewLead(Lead $lead): void
    {
        $notification = new NewLeadNotification($lead);

        if ($lead->owner_id) {
            User::find($lead->owner_id)?->notify($notification);
        } else {
            User::where('is_active', true)
                ->where('role', UserRole::Sales->value)
                ->get()
                ->each(fn (User $u) => $u->notify($notification));
        }
    }
}
