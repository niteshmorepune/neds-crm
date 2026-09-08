<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Support\NextAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 2 of the closure-guidance plan (2026-09-08, confirmed with the
 * owner via AskUserQuestion): a Lead or Deal tagged with a stall_reason
 * (Phase 1) that's gone quiet gets surfaced right in the banner, naming
 * the objection instead of a generic "follow up" nudge. Built directly
 * from the 2026-09-08 VA Funnel diagnosis — every one of the 9 paying
 * customers analysed there had real call history, so "reach out again"
 * alone wasn't the gap; naming *why* it's stuck is.
 *
 * No role gate, same reasoning as CallFollowUpDueSource: a Lead's
 * owner_id (Sales) OR telecaller_id (Telecaller) both count, and a Deal's
 * owner_id (Sales/Manager) does too — whoever's actually working it sees
 * it. Picks the single most stale candidate across both Leads and Deals
 * (oldest last-touch first), matching CallFollowUpDueSource's own
 * "earliest due wins" tie-break.
 */
class ObjectionFollowUpDueSource implements NextActionSource
{
    /** A tagged Lead/Deal with no new touch in this many days surfaces here. */
    private const STALE_DAYS = 3;

    public function key(): string
    {
        return 'objection_follow_up_due';
    }

    public function next(User $user): ?NextAction
    {
        $staleBefore = now()->subDays(self::STALE_DAYS);
        $candidates = $this->leadCandidates($user)->merge($this->dealCandidates($user));

        if ($candidates->isEmpty()) {
            return null;
        }

        $winner = $candidates
            ->filter(fn (array $c) => $c['last_touch'] === null || $c['last_touch']->lte($staleBefore))
            ->sortBy(fn (array $c) => $c['last_touch'] ?? Carbon::createFromTimestamp(0))
            ->first();

        if ($winner === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: $winner['subject_type'],
            subjectId: $winner['subject_id'],
            title: "{$winner['stall_reason']->label()}: {$winner['name']}",
            body: $winner['last_touch'] !== null
                ? "No contact since {$winner['last_touch']->diffForHumans()}."
                : 'No contact logged since this was tagged.',
            actionUrl: $winner['url'],
            actionLabel: $winner['subject_type'] === Lead::class ? 'Open lead' : 'Open deal',
        );
    }

    /**
     * @return Collection<int, array{subject_type: string, subject_id: int, name: string, stall_reason: StallReason, last_touch: ?Carbon, url: string}>
     */
    private function leadCandidates(User $user): Collection
    {
        $snoozedIds = $this->snoozedIds($user, Lead::class);

        return Lead::query()
            ->whereNotNull('stall_reason')
            ->whereIn('status', LeadStatus::openValues())
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('telecaller_id', $user->id))
            ->whereNotIn('id', $snoozedIds)
            ->with(['callLogs:id,callable_id,callable_type,called_at', 'notes:id,notable_id,notable_type,created_at'])
            ->get()
            ->map(fn (Lead $lead) => [
                'subject_type' => Lead::class,
                'subject_id' => $lead->id,
                'name' => $lead->name,
                'stall_reason' => $lead->stall_reason,
                'last_touch' => collect([$lead->callLogs->max('called_at'), $lead->notes->max('created_at')])->filter()->max(),
                'url' => route('leads.show', $lead->id),
            ])
            // An Eloquent Collection mapped to plain arrays is still an
            // Eloquent Collection -- its own merge()/sortBy() assume model
            // items (getKey()). Downgrade to a base Collection so next()'s
            // merge/filter/sortBy over the two candidate sets works on
            // arrays, not models.
            ->pipe(fn ($mapped) => collect($mapped->all()));
    }

    /**
     * @return Collection<int, array{subject_type: string, subject_id: int, name: string, stall_reason: StallReason, last_touch: ?Carbon, url: string}>
     */
    private function dealCandidates(User $user): Collection
    {
        $snoozedIds = $this->snoozedIds($user, Deal::class);

        return Deal::query()
            ->whereNotNull('stall_reason')
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->where('owner_id', $user->id)
            ->whereNotIn('id', $snoozedIds)
            ->with(['notes:id,notable_id,notable_type,created_at', 'customer:id,company_name'])
            ->get()
            ->map(fn (Deal $deal) => [
                'subject_type' => Deal::class,
                'subject_id' => $deal->id,
                'name' => $deal->customer?->company_name ?? $deal->title,
                'stall_reason' => $deal->stall_reason,
                'last_touch' => $deal->notes->max('created_at'),
                'url' => route('deals.show', $deal->id),
            ])
            ->pipe(fn ($mapped) => collect($mapped->all()));
    }

    private function snoozedIds(User $user, string $subjectType): Collection
    {
        return NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', $subjectType)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');
    }

    /**
     * This source's prompt always sets actionUrl (a link to the lead/deal
     * page), so the banner never renders a button for it and this should
     * never be reachable — throwing surfaces a wiring bug loudly instead
     * of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
