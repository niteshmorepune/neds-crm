<?php

use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\LunchHourWadeskAiSource;
use Illuminate\Support\Carbon;

function lunchHourWadeskAiSource(): LunchHourWadeskAiSource
{
    return app(LunchHourWadeskAiSource::class);
}

// A real Monday, so isSunday() checks in the source behave as expected.
const LUNCH_TEST_MONDAY = '2026-09-07';

it('prompts an Admin to turn the AI on right at lunch start', function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 13:00', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();

    $action = lunchHourWadeskAiSource()->next($admin);

    expect($action)->not->toBeNull();
    expect($action->title)->toBe('Turn on lunch-hour AI replies');
    expect($action->actionUrl)->toBe('https://wadesk.in/numbers');
    expect($action->external)->toBeTrue();

    Carbon::setTestNow();
});

it('prompts a Manager to turn the AI back off right at lunch end', function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 14:00', config('app.display_timezone')));
    $manager = User::factory()->role(UserRole::Manager)->create();

    $action = lunchHourWadeskAiSource()->next($manager);

    expect($action)->not->toBeNull();
    expect($action->title)->toBe('Turn off lunch-hour AI replies');

    Carbon::setTestNow();
});

it('does not prompt outside either window', function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 11:00', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();

    expect(lunchHourWadeskAiSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt between the two windows (mid-lunch)', function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 13:30', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();

    expect(lunchHourWadeskAiSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('never prompts a non-Admin/Manager role', function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 13:00', config('app.display_timezone')));
    $sales = User::factory()->role(UserRole::Sales)->create();

    expect(lunchHourWadeskAiSource()->next($sales))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt on a Sunday', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-06 13:00', config('app.display_timezone'))); // a real Sunday
    $admin = User::factory()->role(UserRole::Admin)->create();

    expect(lunchHourWadeskAiSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it("excludes a snoozed window but a different day's same window is unaffected", function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 13:00', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();

    $action = lunchHourWadeskAiSource()->next($admin);
    NextActionSnooze::create([
        'user_id' => $admin->id,
        'source_key' => 'lunch_hour_wadesk_ai',
        'subject_type' => $action->subjectType,
        'subject_id' => $action->subjectId,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(lunchHourWadeskAiSource()->next($admin))->toBeNull();

    // Same clock time, next day — a fresh, unsnoozed subject id.
    Carbon::setTestNow(Carbon::parse('2026-09-08 13:00', config('app.display_timezone')));
    expect(lunchHourWadeskAiSource()->next($admin))->not->toBeNull();

    Carbon::setTestNow();
});

it('the turn-on and turn-off windows on the same day snooze independently', function () {
    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 13:00', config('app.display_timezone')));
    $admin = User::factory()->role(UserRole::Admin)->create();

    $turnOn = lunchHourWadeskAiSource()->next($admin);
    NextActionSnooze::create([
        'user_id' => $admin->id,
        'source_key' => 'lunch_hour_wadesk_ai',
        'subject_type' => $turnOn->subjectType,
        'subject_id' => $turnOn->subjectId,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    Carbon::setTestNow(Carbon::parse(LUNCH_TEST_MONDAY.' 14:00', config('app.display_timezone')));
    expect(lunchHourWadeskAiSource()->next($admin))->not->toBeNull();

    Carbon::setTestNow();
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    lunchHourWadeskAiSource()->complete($admin, 1);
})->throws(RuntimeException::class);
