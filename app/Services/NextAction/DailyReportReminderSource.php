<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\User;
use App\Support\NextAction;
use Illuminate\Support\Carbon;

/**
 * Phase 6 of the Next Action Engine — the "closing the day out" pair
 * (with CheckOutReminderSource). Office hours are 9am-6pm (confirmed with
 * the owner) and `app:send-daily-report-reminders` already emails anyone
 * who hasn't submitted at 18:00 IST — this reuses that exact threshold and
 * the same "on approved leave today" exclusion, rather than inventing a
 * second definition of end-of-day. Applies to every role, no role gate.
 * Registered ahead of the role-specific catch-all sources (LunchHourWadeskAi,
 * the lead-call/ticket-reply sources) so that once it's genuinely evening,
 * closing out the day outranks a stale earlier reminder — confirmed with
 * the owner via AskUserQuestion — but still sits after Attendance and
 * MeetingStartingSoon, since neither an in-progress check-in nor a live
 * meeting should ever be pre-empted by this.
 */
class DailyReportReminderSource implements NextActionSource
{
    private const EVENING_THRESHOLD = '18:00';

    public function key(): string
    {
        return 'daily_report_reminder';
    }

    public function next(User $user): ?NextAction
    {
        $now = Carbon::now(config('app.display_timezone'));

        if ($now->isSunday() || $now->format('H:i') < self::EVENING_THRESHOLD) {
            return null;
        }

        $today = $now->copy()->startOfDay();

        $alreadySubmitted = DailyReport::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->exists();

        if ($alreadySubmitted) {
            return null;
        }

        $onLeave = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->where('status', AttendanceStatus::Leave)
            ->exists();

        if ($onLeave) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: User::class,
            subjectId: $user->id,
            title: 'Submit your daily report',
            body: "It's end of day — let the team know what you worked on today.",
            actionUrl: route('daily-reports.index'),
            actionLabel: 'Submit daily report',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the Daily
     * Report page — free-text summary, not a one-click action), so the
     * banner never renders a button for it and this should never be
     * reachable — throwing surfaces a wiring bug loudly instead of
     * silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
