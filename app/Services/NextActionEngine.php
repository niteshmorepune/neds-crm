<?php

namespace App\Services;

use App\Contracts\NextActionSource;
use App\Models\User;
use App\Services\NextAction\AttendanceCheckInSource;
use App\Services\NextAction\SalesNewLeadCallSource;
use App\Support\NextAction;

/**
 * Computes the single next "do this now" prompt for a user, checking each
 * registered NextActionSource in order and returning the first one with
 * something pending. This list is the whole roadmap surface for adding
 * more per-role flows over time (meeting-starting-soon, etc.) without
 * touching the engine or the popup itself. Attendance is deliberately
 * first — the owner's own "very first task" framing, and it applies to
 * every role, so it should never be shadowed by a role-specific prompt.
 *
 * @see NextActionSource
 */
class NextActionEngine
{
    /** @var array<class-string<NextActionSource>> */
    private const SOURCES = [
        AttendanceCheckInSource::class,
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
