<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use App\Support\NextAction;
use Illuminate\Support\Carbon;

/**
 * The owner's own "very first task" example: every role gets prompted to
 * mark attendance the moment they're on the CRM with no check-in recorded
 * yet today — registered first in NextActionEngine's catalog so it's
 * always resolved before any role-specific prompt. "Today" and the
 * check-in write itself deliberately match AttendanceWidget::checkIn()
 * exactly (bare Carbon::today(), not display_timezone) rather than
 * "fixing" that to Asia/Kolkata here — two different definitions of
 * "today" between this prompt and the dashboard's own check-in widget
 * would be a worse bug than the pre-existing one, and correcting the
 * timezone handling itself is out of scope for this feature.
 */
class AttendanceCheckInSource implements NextActionSource
{
    public function key(): string
    {
        return 'attendance_check_in';
    }

    public function next(User $user): ?NextAction
    {
        $today = Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();

        if ($today?->check_in_at !== null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: User::class,
            subjectId: $user->id,
            title: 'Mark your attendance',
            body: "You haven't checked in yet today.",
            actionUrl: null,
            actionLabel: 'Check in now',
        );
    }

    public function complete(User $user, int $subjectId): void
    {
        abort_unless($subjectId === $user->id, 403);

        Attendance::updateOrCreate(
            ['user_id' => $user->id, 'date' => Carbon::today()->toDateString()],
            ['check_in_at' => now(), 'status' => AttendanceStatus::Present->value],
        );
    }
}
