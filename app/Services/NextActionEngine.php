<?php

namespace App\Services;

use App\Contracts\NextActionSource;
use App\Models\User;
use App\Services\NextAction\AttendanceCheckInSource;
use App\Services\NextAction\CheckOutReminderSource;
use App\Services\NextAction\DailyReportReminderSource;
use App\Services\NextAction\LunchHourWadeskAiSource;
use App\Services\NextAction\MeetingStartingSoonSource;
use App\Services\NextAction\SalesNewLeadCallSource;
use App\Services\NextAction\SupportNewTicketReplySource;
use App\Services\NextAction\TelecallerNewLeadCallSource;
use App\Support\NextAction;

/**
 * Computes the single next "do this now" prompt for a user, checking each
 * registered NextActionSource in order and returning the first one with
 * something pending. This list is the whole roadmap surface for adding
 * more per-role flows over time without touching the engine or the popup
 * itself. Order is deliberate, by urgency, not registration date:
 * Attendance first (the owner's own "very first task" framing — quick,
 * universal, so it should never be shadowed by anything role-specific),
 * then MeetingStartingSoon (genuinely time-critical — missing a meeting
 * start is worse than a few seconds' delay on anything else), then
 * DailyReportReminder and CheckOutReminder — both gated to only ever
 * apply after 6pm (office hours end, confirmed with the owner), so they
 * never affect daytime behavior at all, but once evening genuinely
 * arrives they deliberately outrank everything below them (confirmed
 * with the owner via AskUserQuestion) — closing the day out matters more
 * than a stale, ignored lead-call/ticket reminder from earlier. Report
 * before check-out, matching the owner's own "submit report, then check
 * out" framing of the day's last two steps. Then LunchHourWadeskAi (a
 * narrow ~15-minute window, still worth surfacing ahead of a lead call
 * that can wait), then the three "call/respond now" role-specific
 * sources (Sales/Telecaller lead calls, Support ticket replies) —
 * important, but never as time-boxed as anything ahead of them. These
 * three are mutually exclusive for almost everyone (gated on different
 * roles), so their relative order rarely matters in practice.
 *
 * @see NextActionSource
 */
class NextActionEngine
{
    /** @var array<class-string<NextActionSource>> */
    private const SOURCES = [
        AttendanceCheckInSource::class,
        MeetingStartingSoonSource::class,
        DailyReportReminderSource::class,
        CheckOutReminderSource::class,
        LunchHourWadeskAiSource::class,
        SalesNewLeadCallSource::class,
        TelecallerNewLeadCallSource::class,
        SupportNewTicketReplySource::class,
    ];

    public function nextFor(User $user): ?NextAction
    {
        foreach (self::SOURCES as $sourceClass) {
            $prompt = app($sourceClass)->next($user);

            if ($prompt !== null) {
                return $prompt;
            }
        }

        return null;
    }

    public function completeFor(User $user, string $sourceKey, int $subjectId): void
    {
        foreach (self::SOURCES as $sourceClass) {
            $source = app($sourceClass);

            if ($source->key() === $sourceKey) {
                $source->complete($user, $subjectId);

                return;
            }
        }

        abort(404);
    }
}
