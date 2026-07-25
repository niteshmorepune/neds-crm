<?php

namespace App\Console\Commands;

use App\Enums\NudgeRecurrence;
use App\Models\TeamNudge;
use App\Models\User;
use Illuminate\Console\Command;

class RolloverTeamNudges extends Command
{
    protected $signature = 'app:rollover-team-nudges';

    protected $description = 'Create a fresh pending status row for the current week for every active weekly TeamNudge and its targeted users.';

    public function handle(): int
    {
        $periodStart = TeamNudge::currentPeriodStart();
        $created = 0;

        $nudges = TeamNudge::query()
            ->active()
            ->where('recurrence', NudgeRecurrence::Weekly->value)
            ->get();

        foreach ($nudges as $nudge) {
            $users = $nudge->target_role === null
                ? User::where('is_active', true)->get()
                : User::where('is_active', true)->withAnyRole($nudge->target_role)->get();

            foreach ($users as $user) {
                $status = $nudge->statuses()->firstOrCreate([
                    'user_id' => $user->id,
                    'period_start' => $periodStart,
                ], [
                    'status' => 'pending',
                ]);

                if ($status->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->info("Rolled over {$nudges->count()} weekly nudge(s), created {$created} new status row(s) for period {$periodStart->toDateString()}.");

        return self::SUCCESS;
    }
}
