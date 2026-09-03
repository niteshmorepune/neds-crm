<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\User;
use App\Services\NextAction\DailyReportReminderSource;
use Illuminate\Support\Carbon;

function dailyReportReminderSource(): DailyReportReminderSource
{
    return app(DailyReportReminderSource::class);
}

// A real Monday, so isSunday() checks behave as expected.
const REPORT_TEST_MONDAY = '2026-09-07';

it('prompts to submit a report once past 6pm with nothing submitted yet', function () {
    Carbon::setTestNow(Carbon::parse(REPORT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();

    $action = dailyReportReminderSource()->next($user);

    expect($action)->not->toBeNull();
    expect($action->title)->toBe('Submit your daily report');
    expect($action->actionUrl)->toBe(route('daily-reports.index'));

    Carbon::setTestNow();
});

it('does not prompt before 6pm', function () {
    Carbon::setTestNow(Carbon::parse(REPORT_TEST_MONDAY.' 17:59', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();

    expect(dailyReportReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt once a report is already submitted today', function () {
    Carbon::setTestNow(Carbon::parse(REPORT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    DailyReport::factory()->create(['user_id' => $user->id, 'date' => Carbon::today(config('app.display_timezone'))]);

    expect(dailyReportReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt someone on approved leave today', function () {
    Carbon::setTestNow(Carbon::parse(REPORT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['status' => AttendanceStatus::Leave, 'check_in_at' => null]);

    expect(dailyReportReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt on a Sunday even after 6pm', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-06 18:05', config('app.display_timezone'))); // a real Sunday
    $user = User::factory()->role(UserRole::Support)->create();

    expect(dailyReportReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $user = User::factory()->role(UserRole::Support)->create();

    dailyReportReminderSource()->complete($user, $user->id);
})->throws(RuntimeException::class);
