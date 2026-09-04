<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Models\Attendance;
use App\Models\User;
use App\Support\NextAction;
use Illuminate\Support\Carbon;

/**
 * Phase 6 of the Next Action Engine — the true last task of the day (see
 * DailyReportReminderSource, registered just ahead of this one, for the
 * shared "why 18:00" rationale). Once checked in with no check-out yet,
 * and past the 6pm office-hours close, prompt to check out. "Today" and
 * the check-out write itself deliberately match AttendanceWidget::
 * checkOut() exactly (bare Carbon::today(), not display_timezone) — same
 * reasoning as AttendanceCheckInSource: two different definitions of
 * "today" between this prompt and the dashboard's own widget would be a
 * worse bug than the pre-existing one. Only the evening-threshold check
 * itself uses display_timezone, mirroring app:send-daily-report-reminders'
 * own convention for "is it end of day yet."
 */
class CheckOutReminderSource implements NextActionSource
{
    private const EVENING_THRESHOLD = '18:00';

    public function key(): string
    {
        return 'checkout_reminder';
    }

    public function next(User $user): ?NextAction
    {
        $now = Carbon::now(config('app.display_timezone'));

        if ($now->isSunday() || $now->format('H:i') < self::EVENING_THRESHOLD) {
            return null;
        }

        $today = $this->todayAttendance($user);

        if ($today === null || $today->check_in_at === null || $today->check_out_at !== null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: User::class,
            subjectId: $user->id,
            title: 'Check out for the day',
            body: 'Wrap up — mark your check-out time before you go.',
            actionUrl: null,
            actionLabel: 'Check out now',
        );
    }

    /**
     * A double-click (or a request that lands after the banner's own poll
     * already cleared this prompt) hits this with checkout already
     * recorded — silently no-ops rather than aborting, since the desired
     * end state (checked out) is already true. Mirrors
     * AttendanceCheckInSource::complete()'s own idempotent style, which
     * gets this for free via updateOrCreate(); this one needs an explicit
     * guard since it only ever updates an existing row. Real incident
     * 2026-09-04: a fast second click surfaced Laravel's raw 422 error
     * page instead of just doing nothing.
     */
    public function complete(User $user, int $subjectId): void
    {
        abort_unless($subjectId === $user->id, 403);

        $today = $this->todayAttendance($user);

        if ($today === null || $today->check_in_at === null || $today->check_out_at !== null) {
            return;
        }

        $today->update(['check_out_at' => now()]);
    }

    private function todayAttendance(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();
    }
}
