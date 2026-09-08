<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\DraftLeadStallFollowUp;
use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Console\Command;

/**
 * Lead-side counterpart to App\Console\Commands\DraftDealStallFollowUps,
 * with one deliberate difference: this only ever fires for a lead the
 * team has explicitly tagged with a stall_reason, not any quiet lead.
 * DraftDealStallFollowUps predates the closure-guidance plan and already
 * fires unconditionally for any quiet deal (left as-is -- changing an
 * already-shipped automation's trigger is a bigger, separate decision);
 * this new command is being built fresh alongside Phases 1-3, which made
 * explicit tagging the whole mechanism ("tag it, and the system helps you
 * -- don't tag it, and nothing fires automatically"). Leads are also much
 * higher-volume than Deals, so an unconditional trigger here would draft
 * far more than the team could realistically review.
 */
class DraftLeadStallFollowUps extends Command
{
    protected $signature = 'app:draft-lead-stall-followups
                            {--days=7 : Days without any call, note, or logged edit before a stall-tagged open lead is considered stale enough to draft}';

    protected $description = 'Queue an AI-drafted check-in note for any open, stall-tagged lead that has gone quiet for N days.';

    public function handle(): int
    {
        if (now(config('app.display_timezone'))->isSunday()) {
            $this->info('Sunday — skipping lead stall drafts.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $leads = Lead::query()
            ->whereNotNull('stall_reason')
            ->whereIn('status', LeadStatus::openValues())
            ->whereNotNull('owner_id')
            ->where('leads.created_at', '<=', $cutoff)
            ->whereDoesntHave('activities', fn ($q) => $q->where('activities.created_at', '>', $cutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('notes.created_at', '>', $cutoff))
            ->whereDoesntHave('callLogs', fn ($q) => $q->where('call_logs.called_at', '>', $cutoff))
            ->get();

        $dispatched = 0;

        foreach ($leads as $lead) {
            if ($this->alreadyDrafted($lead)) {
                continue;
            }

            DraftLeadStallFollowUp::dispatch($lead->id);
            $dispatched++;
        }

        $this->info("Done — {$dispatched} lead stall follow-up(s) dispatched.");

        return self::SUCCESS;
    }

    private function alreadyDrafted(Lead $lead): bool
    {
        return Activity::where('subject_type', Lead::class)
            ->where('subject_id', $lead->id)
            ->where('event', DraftLeadStallFollowUp::ACTIVITY_EVENT)
            ->where('created_at', '>=', $lead->lastTouchedAt())
            ->exists();
    }
}
