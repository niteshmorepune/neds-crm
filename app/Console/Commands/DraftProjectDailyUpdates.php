<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Jobs\DraftProjectDailyUpdate;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DraftProjectDailyUpdates extends Command
{
    protected $signature = 'app:draft-project-daily-updates';

    protected $description = 'Queue AI-drafted client update notes for active projects with activity today (run at 18:30 IST).';

    public function handle(): int
    {
        $today = Carbon::today(config('app.display_timezone'));

        if ($today->isSunday()) {
            $this->info('Sunday — skipping project daily updates.');

            return self::SUCCESS;
        }

        $date = $today->toDateString();

        $projects = Project::where('status', ProjectStatus::Active)->get(['id']);

        if ($projects->isEmpty()) {
            $this->info('No active projects.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($projects as $project) {
            $alreadyDrafted = Activity::where('subject_type', Project::class)
                ->where('subject_id', $project->id)
                ->where('event', DraftProjectDailyUpdate::ACTIVITY_EVENT)
                ->whereJsonContains('changes->date', $date)
                ->exists();

            if ($alreadyDrafted) {
                $skipped++;

                continue;
            }

            DraftProjectDailyUpdate::dispatch($project->id, $date);
            $dispatched++;
        }

        $this->info("Done for {$date} — {$dispatched} dispatched, {$skipped} already drafted.");

        return self::SUCCESS;
    }
}
