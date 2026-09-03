<?php

namespace App\Services;

use App\Contracts\NextActionSource;
use App\Models\User;
use App\Services\NextAction\AttendanceCheckInSource;
use App\Services\NextAction\LunchHourWadeskAiSource;
use App\Services\NextAction\MeetingStartingSoonSource;
use App\Services\NextAction\SalesNewLeadCallSource;
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
 * LunchHourWadeskAi (a narrow ~15-minute window, still worth surfacing
 * ahead of a lead call that can wait), then SalesNewLeadCall (important,
 * but never as time-boxed as the sources ahead of it).
 *
 * @see NextActionSource
 */
class NextActionEngine
{
    /** @var array<class-string<NextActionSource>> */
    private const SOURCES = [
        AttendanceCheckInSource::class,
        MeetingStartingSoonSource::class,
        LunchHourWadeskAiSource::class,
        SalesNewLeadCallSource::class,
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
