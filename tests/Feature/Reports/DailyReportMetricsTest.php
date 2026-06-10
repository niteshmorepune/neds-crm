<?php

use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\DailyReportMetrics;
use Illuminate\Support\Carbon;

it('auto-compiles a user\'s metrics for the day', function () {
    $user = $user = User::factory()->create();
    $today = Carbon::today();

    // 2 completed tasks for the user today, 1 not-done (ignored).
    Task::factory()->count(2)->assignedTo($user->id)->status(TaskStatus::Done)->create();
    Task::factory()->assignedTo($user->id)->status(TaskStatus::Todo)->create();
    // someone else's done task (ignored)
    Task::factory()->status(TaskStatus::Done)->create();

    CallLog::factory()->count(3)->create(['user_id' => $user->id, 'called_at' => now()]);
    Lead::factory()->ownedBy($user->id)->create(); // touched today (updated_at = now)
    Attendance::factory()->create(['user_id' => $user->id, 'date' => $today, 'status' => 'present']);

    $metrics = app(DailyReportMetrics::class)->for($user, $today);

    expect($metrics)->toMatchArray([
        'tasks_completed' => 2,
        'calls_made' => 3,
        'leads_touched' => 1,
        'attendance_status' => 'present',
    ]);
});
