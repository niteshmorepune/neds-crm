<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\StallReasonMetrics;
use App\Support\NextAction;
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
 * Candidate data (which records, whose, how stale) comes entirely from
 * StallReasonMetrics — the same source Phase 3's Stalling page reads, so
 * this banner and that page can never silently disagree on what counts
 * as stalling. This class only adds what's specific to a Next Action
 * prompt: the staleness cutoff and snooze exclusion.
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

    public function __construct(private readonly StallReasonMetrics $metrics) {}

    public function key(): string
    {
        return 'objection_follow_up_due';
    }

    public function next(User $user): ?NextAction
    {
        $staleBefore = now()->subDays(self::STALE_DAYS);
        $snoozedLeadIds = $this->snoozedIds($user, Lead::class);
        $snoozedDealIds = $this->snoozedIds($user, Deal::class);

        $winner = $this->metrics->all($user->id)
            ->reject(fn (array $c) => $c['subject_type'] === Lead::class && $snoozedLeadIds->contains($c['subject_id']))
            ->reject(fn (array $c) => $c['subject_type'] === Deal::class && $snoozedDealIds->contains($c['subject_id']))
            ->filter(fn (array $c) => $c['last_touch']->lte($staleBefore))
            ->sortBy('last_touch')
            ->first();

        if ($winner === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: $winner['subject_type'],
            subjectId: $winner['subject_id'],
            title: "{$winner['stall_reason']->label()}: {$winner['name']}",
            body: "No contact since {$winner['last_touch']->diffForHumans()}.",
            actionUrl: $winner['url'],
            actionLabel: $winner['subject_type'] === Lead::class ? 'Open lead' : 'Open deal',
        );
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
