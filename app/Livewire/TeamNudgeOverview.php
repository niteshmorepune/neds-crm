<?php

namespace App\Livewire;

use App\Enums\NudgeRecurrence;
use App\Enums\NudgeStatus;
use App\Models\TeamNudge;
use App\Models\User;
use Livewire\Component;

/**
 * Admin/Manager-only team-wide completion overview — reachable only via
 * team-nudges/index.blade.php, which TeamNudgeController::index() already
 * restricts via TeamNudgePolicy::viewAny(), so no separate auth check is
 * needed here (same reachability-guarantee pattern as TeamPerformanceSummary).
 *
 * Deliberately derives the targeted-user list directly (not from existing
 * TeamNudgeStatus rows) so a user who hasn't opened their dashboard yet still
 * shows as "Pending" rather than silently missing from the table — and
 * snoozed is shown as its own status, never folded into "Done", so a snooze
 * can never hide the real completion picture from oversight.
 */
class TeamNudgeOverview extends Component
{
    public function render()
    {
        $periodStart = TeamNudge::currentPeriodStart();

        $table = TeamNudge::query()->active()->get()->map(function (TeamNudge $nudge) use ($periodStart) {
            $users = $nudge->target_role === null
                ? User::where('is_active', true)->get()
                : User::where('is_active', true)->withAnyRole($nudge->target_role)->get();

            $key = $nudge->recurrence === NudgeRecurrence::Weekly ? $periodStart : null;

            $statusesByUser = $nudge->statuses()->where('period_start', $key)->get()->keyBy('user_id');

            $rows = $users->map(function (User $user) use ($statusesByUser) {
                $status = $statusesByUser->get($user->id);

                return [
                    'user' => $user,
                    'status' => $status?->status ?? NudgeStatus::Pending,
                    'completed_via' => $status?->completed_via,
                    'completed_at' => $status?->completed_at,
                ];
            })->values();

            return [
                'nudge' => $nudge,
                'rows' => $rows,
                'done_count' => $rows->filter(fn (array $r) => $r['status'] === NudgeStatus::Done)->count(),
                'total_count' => $rows->count(),
            ];
        });

        return view('livewire.team-nudge-overview', ['table' => $table]);
    }
}
