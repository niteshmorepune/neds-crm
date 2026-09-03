<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use App\Services\NextAction\AttendanceCheckInSource;
use Symfony\Component\HttpKernel\Exception\HttpException;

function attendanceCheckInSource(): AttendanceCheckInSource
{
    return app(AttendanceCheckInSource::class);
}

it('prompts any role to check in when nothing is recorded for today', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    $action = attendanceCheckInSource()->next($user);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($user->id);
    expect($action->actionUrl)->toBeNull();
    expect($action->actionLabel)->toBe('Check in now');
})->with([UserRole::Sales, UserRole::Support, UserRole::Accounts, UserRole::Admin]);

it("prompts when today's row exists but has no check-in time yet", function () {
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['check_in_at' => null, 'status' => AttendanceStatus::Absent]);

    expect(attendanceCheckInSource()->next($user))->not->toBeNull();
});

it('returns null once checked in today', function () {
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create();

    expect(attendanceCheckInSource()->next($user))->toBeNull();
});

it("does not consider yesterday's check-in as covering today", function () {
    $user = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->for($user)->create(['date' => now()->subDay()->toDateString()]);

    expect(attendanceCheckInSource()->next($user))->not->toBeNull();
});

it('complete() checks the user in for today', function () {
    $user = User::factory()->role(UserRole::Support)->create();

    attendanceCheckInSource()->complete($user, $user->id);

    $attendance = Attendance::where('user_id', $user->id)->whereDate('date', now())->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->check_in_at)->not->toBeNull();
    expect($attendance->status)->toBe(AttendanceStatus::Present);

    expect(attendanceCheckInSource()->next($user))->toBeNull();
});

it('refuses to check in on behalf of a different user', function () {
    $user = User::factory()->role(UserRole::Support)->create();
    $other = User::factory()->role(UserRole::Support)->create();

    attendanceCheckInSource()->complete($user, $other->id);
})->throws(HttpException::class);
