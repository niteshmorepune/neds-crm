<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Auto-compiles a user's activity metrics for a given day, used to pre-fill the
 * end-of-day daily report.
 */
class DailyReportMetrics
{
    /**
     * @return array{tasks_completed:int, calls_made:int, leads_touched:int, attendance_status:?string}
     */
    public function for(User $user, Carbon $date): array
    {
        $day = $date->toDateString();

        return [
            'tasks_completed' => Task::where('assignee_id', $user->id)
                ->where('status', TaskStatus::Done->value)
                ->whereDate('updated_at', $day)
                ->count(),

            'calls_made' => CallLog::where('user_id', $user->id)
                ->whereDate('called_at', $day)
                ->count(),

            'leads_touched' => Lead::where('owner_id', $user->id)
                ->whereDate('updated_at', $day)
                ->count(),

            'attendance_status' => Attendance::where('user_id', $user->id)
                ->whereDate('date', $day)
                ->value('status')?->value,
        ];
    }
}
