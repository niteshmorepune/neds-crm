<?php

namespace App\Console\Commands;

use App\Enums\NudgeStatus;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use App\Services\TeamNudgeDetector;
use Illuminate\Console\Command;

class RunTeamNudgeAutoDetect extends Command
{
    protected $signature = 'app:run-team-nudge-auto-detect';

    protected $description = 'Auto-clear pending TeamNudgeStatus rows whose nudge has an auto_detect_type, the moment real matching activity exists.';

    public function handle(TeamNudgeDetector $detector): int
    {
        $periodStart = TeamNudge::currentPeriodStart();
        $cleared = 0;

        $statuses = TeamNudgeStatus::query()
            ->where('status', NudgeStatus::Pending->value)
            ->where('period_start', $periodStart)
            ->whereHas('nudge', fn ($q) => $q->active()->whereNotNull('auto_detect_type'))
            ->with(['nudge', 'user'])
            ->get();

        foreach ($statuses as $status) {
            if ($detector->check($status->nudge->auto_detect_type, $status->user, $periodStart)) {
                $status->update([
                    'status' => NudgeStatus::Done->value,
                    'completed_via' => 'auto',
                    'completed_at' => now(),
                ]);
                $cleared++;
            }
        }

        $this->info("Checked {$statuses->count()} pending auto-detect nudge(s), auto-cleared {$cleared}.");

        return self::SUCCESS;
    }
}
