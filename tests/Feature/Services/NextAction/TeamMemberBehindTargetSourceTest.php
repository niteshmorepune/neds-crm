<?php

use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\RoleTarget;
use App\Models\Task;
use App\Models\User;
use App\Services\NextAction\TeamMemberBehindTargetSource;
use Illuminate\Support\Carbon;

function teamMemberBehindTargetSource(): TeamMemberBehindTargetSource
{
    return app(TeamMemberBehindTargetSource::class);
}

// The 20th of a real month — safely past MIN_DAY_OF_MONTH (7), with the
// month roughly two-thirds elapsed (~65-67%), a stable reference point for
// the "behind" math. .utc() avoids the Carbon testNow-timezone-leak gotcha
// (see [[feedback-gotchas]]) even though this source doesn't itself compare
// a date-cast attribute via isPast()-style calls — cheap insurance.
const BEHIND_TEST_MID_MONTH = '2026-09-20 11:00';

const BEHIND_TEST_EARLY_MONTH = '2026-09-03 11:00';

function targetedIntern(int $targetTasks, int $completedTasks): User
{
    $intern = User::factory()->role(UserRole::Intern)->create();

    RoleTarget::factory()->forUser($intern->id, TargetMetric::TasksCompleted)->create([
        'period_type' => TargetPeriodType::Month,
        'period_start' => TargetPeriodType::Month->currentPeriodStart(),
        'target_value' => $targetTasks,
    ]);

    for ($i = 0; $i < $completedTasks; $i++) {
        Task::factory()->create([
            'assignee_id' => $intern->id,
            'status' => TaskStatus::Done,
            'completed_at' => now()->subDays(2),
        ]);
    }

    return $intern;
}

it('returns null for a non-Admin/Manager user', function () {
    Carbon::setTestNow(Carbon::parse(BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $sales = User::factory()->role(UserRole::Sales)->create();
    targetedIntern(targetTasks: 100, completedTasks: 5);

    expect(teamMemberBehindTargetSource()->next($sales))->toBeNull();

    Carbon::setTestNow();
});

it('prompts an Admin to check in with a team member well behind their monthly target', function () {
    Carbon::setTestNow(Carbon::parse(BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    $intern = targetedIntern(targetTasks: 100, completedTasks: 5); // 5% actual vs ~65% of month elapsed

    $action = teamMemberBehindTargetSource()->next($admin);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($intern->id);
    expect($action->title)->toBe("Check in with {$intern->name}");
    expect($action->actionUrl)->toBe(route('employees.show', $intern));

    Carbon::setTestNow();
});

it('does not flag someone comfortably on pace', function () {
    Carbon::setTestNow(Carbon::parse(BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    targetedIntern(targetTasks: 10, completedTasks: 7); // 70% actual vs ~65% of month elapsed

    expect(teamMemberBehindTargetSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('does not flag anyone with no target set at all', function () {
    Carbon::setTestNow(Carbon::parse(BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    User::factory()->role(UserRole::Intern)->create(); // no RoleTarget row

    expect(teamMemberBehindTargetSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('does not flag anyone before the first week of the month', function () {
    Carbon::setTestNow(Carbon::parse(BEHIND_TEST_EARLY_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    targetedIntern(targetTasks: 100, completedTasks: 0);

    expect(teamMemberBehindTargetSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('excludes a snoozed team member but includes them again once the snooze expires', function () {
    Carbon::setTestNow(Carbon::parse(BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    $intern = targetedIntern(targetTasks: 100, completedTasks: 5);

    NextActionSnooze::create([
        'user_id' => $admin->id,
        'source_key' => 'team_member_behind_target',
        'subject_type' => User::class,
        'subject_id' => $intern->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(teamMemberBehindTargetSource()->next($admin))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(teamMemberBehindTargetSource()->next($admin)?->subjectId)->toBe($intern->id);

    Carbon::setTestNow();
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    teamMemberBehindTargetSource()->complete($admin, $admin->id);
})->throws(RuntimeException::class);
