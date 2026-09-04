<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use App\Services\NextAction\CheckOutReminderSource;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

function checkOutReminderSource(): CheckOutReminderSource
{
    return app(CheckOutReminderSource::class);
}

const CHECKOUT_TEST_MONDAY = '2026-09-07';

it('prompts to check out once past 6pm when checked in but not out', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_in_at' => Carbon::parse(CHECKOUT_TEST_MONDAY.' 09:30'), 'check_out_at' => null]);

    $action = checkOutReminderSource()->next($user);

    expect($action)->not->toBeNull();
    expect($action->title)->toBe('Check out for the day');
    expect($action->actionUrl)->toBeNull();
    expect($action->actionLabel)->toBe('Check out now');

    Carbon::setTestNow();
});

it('does not prompt before 6pm', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 17:59', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_out_at' => null]);

    expect(checkOutReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt someone who never checked in', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();

    expect(checkOutReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt someone who already checked out', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_out_at' => now()]);

    expect(checkOutReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('does not prompt on a Sunday even after 6pm', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-06 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_out_at' => null]);

    expect(checkOutReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('complete() checks the user out, recording the current time', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_in_at' => Carbon::parse(CHECKOUT_TEST_MONDAY.' 09:30'), 'check_out_at' => null]);

    checkOutReminderSource()->complete($user, $user->id);

    $attendance = Attendance::where('user_id', $user->id)->whereDate('date', Carbon::today())->first();
    expect($attendance->check_out_at)->not->toBeNull();
    expect(checkOutReminderSource()->next($user))->toBeNull();

    Carbon::setTestNow();
});

it('complete() silently no-ops on a second call once already checked out, instead of erroring', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_in_at' => Carbon::parse(CHECKOUT_TEST_MONDAY.' 09:30'), 'check_out_at' => null]);

    checkOutReminderSource()->complete($user, $user->id);
    $firstCheckOutAt = Attendance::where('user_id', $user->id)->whereDate('date', Carbon::today())->first()->check_out_at;

    // A fast double-click (or a stale request) must not throw.
    checkOutReminderSource()->complete($user, $user->id);

    $attendance = Attendance::where('user_id', $user->id)->whereDate('date', Carbon::today())->first();
    expect($attendance->check_out_at->eq($firstCheckOutAt))->toBeTrue();

    Carbon::setTestNow();
});

it('refuses to check out on behalf of a different user', function () {
    Carbon::setTestNow(Carbon::parse(CHECKOUT_TEST_MONDAY.' 18:05', config('app.display_timezone')));
    $user = User::factory()->role(UserRole::Support)->create();
    $other = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_out_at' => null]);

    checkOutReminderSource()->complete($user, $other->id);

    Carbon::setTestNow();
})->throws(HttpException::class);
