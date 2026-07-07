<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Jobs\DraftLeadNurtureFollowUp;
use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Console\Command;

class DraftLeadNurtureFollowUps extends Command
{
    protected $signature = 'app:draft-lead-nurture-followups';

    protected $description = 'Queue AI-drafted follow-ups for New leads nobody has personally followed up on yet (day 1 / 3 / 7 since they enquired).';

    /** Days since enquiry => nurture touch number. */
    private const TOUCHES = [1 => 1, 3 => 2, 7 => 3];

    public function handle(): int
    {
        if (now(config('app.display_timezone'))->isSunday()) {
            $this->info('Sunday — skipping lead nurture drafts.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach (self::TOUCHES as $days => $touch) {
            $cutoff = now()->subDays($days);

            $leads = Lead::query()
                ->where('status', LeadStatus::New->value)
                ->whereNotNull('owner_id')
                ->where('created_at', '<=', $cutoff)
                // "Untouched" means no staff-authored note and no logged call —
                // the system note created from the original enquiry (website
                // form / WhatsApp message) has user_id null and doesn't count.
                ->whereDoesntHave('notes', fn ($q) => $q->whereNotNull('user_id'))
                ->whereDoesntHave('callLogs')
                ->get();

            foreach ($leads as $lead) {
                if ($this->alreadyDrafted($lead->id, $touch)) {
                    continue;
                }

                DraftLeadNurtureFollowUp::dispatch($lead->id, $touch);
                $dispatched++;
            }
        }

        $this->info("Done — {$dispatched} nurture follow-up(s) dispatched.");

        return self::SUCCESS;
    }

    private function alreadyDrafted(int $leadId, int $touch): bool
    {
        return Activity::where('subject_type', Lead::class)
            ->where('subject_id', $leadId)
            ->where('event', DraftLeadNurtureFollowUp::ACTIVITY_EVENT)
            ->whereJsonContains('changes->touch', $touch)
            ->exists();
    }
}
