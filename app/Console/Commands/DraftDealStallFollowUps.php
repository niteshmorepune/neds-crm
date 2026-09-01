<?php

namespace App\Console\Commands;

use App\Enums\DealStage;
use App\Jobs\DraftDealStallFollowUp;
use App\Models\Activity;
use App\Models\Deal;
use Illuminate\Console\Command;

/**
 * Deal-side counterpart to DraftLeadNurtureFollowUps, but for a different
 * shape of staleness: a New lead nurtured on a fixed 1/3/7-day cadence never
 * qualifies again once touched, whereas a Deal here can go quiet, get
 * worked, then go quiet again indefinitely -- so this checks rolling
 * "no activity in N days" (matching SendStagnationAlerts' own definition of
 * a stagnant deal) rather than a one-shot cadence, and its dedup
 * (alreadyDrafted()) re-arms after any genuine new touch instead of being a
 * permanent per-lead flag. The actual draft-and-notify mechanism (a
 * staff-only note, never sent, owner notified) is otherwise identical to
 * Nurture's.
 */
class DraftDealStallFollowUps extends Command
{
    protected $signature = 'app:draft-deal-stall-followups
                            {--days=7 : Days without any note or logged edit before an open deal is considered stalled}';

    protected $description = 'Queue an AI-drafted check-in note for any open deal that has gone quiet for N days.';

    public function handle(): int
    {
        if (now(config('app.display_timezone'))->isSunday()) {
            $this->info('Sunday — skipping deal stall drafts.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);
        $closedStages = [DealStage::Won->value, DealStage::Lost->value];

        $deals = Deal::query()
            ->whereNotIn('stage', $closedStages)
            ->whereNotNull('owner_id')
            ->where('deals.created_at', '<=', $cutoff)
            ->whereDoesntHave('activities', fn ($q) => $q->where('activities.created_at', '>', $cutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('notes.created_at', '>', $cutoff))
            ->get();

        $dispatched = 0;

        foreach ($deals as $deal) {
            if ($this->alreadyDrafted($deal)) {
                continue;
            }

            DraftDealStallFollowUp::dispatch($deal->id);
            $dispatched++;
        }

        $this->info("Done — {$dispatched} deal stall follow-up(s) dispatched.");

        return self::SUCCESS;
    }

    private function alreadyDrafted(Deal $deal): bool
    {
        return Activity::where('subject_type', Deal::class)
            ->where('subject_id', $deal->id)
            ->where('event', DraftDealStallFollowUp::ACTIVITY_EVENT)
            ->where('created_at', '>=', $deal->lastTouchedAt())
            ->exists();
    }
}
