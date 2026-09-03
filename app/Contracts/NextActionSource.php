<?php

namespace App\Contracts;

use App\Models\User;
use App\Support\NextAction;

/**
 * One entry in the NextActionEngine's ordered catalog. Each source owns one
 * kind of "do this now" prompt for one role/situation — the engine just
 * asks each in turn and shows the first one that has something pending.
 * Deliberately a bounded registry (same discipline as CrmQueryCatalog /
 * NudgeAutoDetectType), not a place for free-form or AI-decided logic —
 * sequencing stays deterministic and testable.
 */
interface NextActionSource
{
    /** Stable identifier used for snoozing — never renamed once shipped. */
    public function key(): string;

    /** The single most pressing pending prompt for this user, or null if nothing from this source is pending. */
    public function next(User $user): ?NextAction;
}
