<?php

namespace App\Livewire;

use App\Enums\NudgeRecurrence;
use App\Enums\NudgeStatus;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use Livewire\Component;

/**
 * Dashboard widget: the viewer's own pending/snoozed Team Nudges (see
 * App\Models\TeamNudge). Embedded on every role's dashboard partial —
 * target_role=null nudges reach everyone, a set target_role is matched via
 * hasRole() so an additional role also sees it (TeamNudge::scopeForUser()).
 * Lazily materializes its own TeamNudgeStatus row if the scheduled
 * rollover/auto-detect commands haven't touched this nudge+user+period yet
 * (e.g. a brand new nudge, or a user hired mid-week).
 */
class MyTeamNudges extends Component
{
    public function markDone(int $statusId): void
    {
        $status = TeamNudgeStatus::findOrFail($statusId);
        abort_unless($status->user_id === auth()->id(), 403);

        $status->update([
            'status' => NudgeStatus::Done->value,
            'completed_via' => 'manual',
            'completed_at' => now(),
        ]);
    }

    public function snooze(int $statusId): void
    {
        $status = TeamNudgeStatus::findOrFail($statusId);
        abort_unless($status->user_id === auth()->id(), 403);

        $status->update([
            'status' => NudgeStatus::Snoozed->value,
            'snoozed_until' => now()->addDays(3),
        ]);
    }

    public function render()
    {
        $user = auth()->user();
        $periodStart = TeamNudge::currentPeriodStart();

        $rows = TeamNudge::query()
            ->active()
            ->forUser($user)
            ->get()
            ->map(function (TeamNudge $nudge) use ($user, $periodStart) {
                $key = $nudge->recurrence === NudgeRecurrence::Weekly ? $periodStart : null;

                $status = $nudge->statuses()->firstOrCreate(
                    ['user_id' => $user->id, 'period_start' => $key],
                    ['status' => NudgeStatus::Pending->value],
                );

                return ['nudge' => $nudge, 'status' => $status];
            })
            ->reject(fn (array $row) => $row['status']->status === NudgeStatus::Done
                || ($row['status']->status === NudgeStatus::Snoozed && $row['status']->isCurrentlySnoozed()))
            ->values();

        return view('livewire.my-team-nudges', ['rows' => $rows]);
    }
}
