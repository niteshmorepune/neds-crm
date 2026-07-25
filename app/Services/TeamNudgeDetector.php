<?php

namespace App\Services;

use App\Enums\NudgeAutoDetectType;
use App\Models\Deal;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Maps each NudgeAutoDetectType to a real Eloquent existence check — the
 * scheduled auto-detect command is the only caller, and it never trusts
 * anything beyond "does this closed set of checks say true or false" for a
 * given user and period. Mirrors the CrmQueryCatalog discipline: the type is
 * a bounded registry, not a free-form condition.
 */
class TeamNudgeDetector
{
    public function check(NudgeAutoDetectType $type, User $user, CarbonInterface $periodStart): bool
    {
        return match ($type) {
            NudgeAutoDetectType::DealsLoggedThisWeek => Deal::where('owner_id', $user->id)
                ->where('created_at', '>=', $periodStart)
                ->exists(),
            NudgeAutoDetectType::TicketsLoggedThisWeek => Ticket::where('created_by', $user->id)
                ->where('created_at', '>=', $periodStart)
                ->exists(),
        };
    }
}
