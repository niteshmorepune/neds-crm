<?php

use App\Enums\NudgeAutoDetectType;
use App\Enums\NudgeRecurrence;
use App\Enums\NudgeStatus;
use App\Enums\UserRole;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function makeSupportNudgeAndPendingStatus(): array
{
    $nudge = TeamNudge::factory()->create([
        'target_role' => UserRole::Support->value,
        'recurrence' => NudgeRecurrence::Weekly->value,
        'auto_detect_type' => NudgeAutoDetectType::TicketsLoggedThisWeek->value,
    ]);
    $user = User::factory()->role(UserRole::Support)->create();
    $status = TeamNudgeStatus::factory()->create([
        'team_nudge_id' => $nudge->id,
        'user_id' => $user->id,
        'period_start' => TeamNudge::currentPeriodStart(),
        'status' => NudgeStatus::Pending->value,
    ]);

    return [$nudge, $user, $status];
}

it('auto-clears a pending nudge once the targeted user has logged a real Ticket this period', function () {
    [, $user, $status] = makeSupportNudgeAndPendingStatus();

    Ticket::factory()->create(['created_by' => $user->id, 'created_at' => now()]);

    Artisan::call('app:run-team-nudge-auto-detect');

    $status->refresh();
    expect($status->status)->toBe(NudgeStatus::Done)
        ->and($status->completed_via)->toBe('auto');
});

it('does not auto-clear when a DIFFERENT user logged the ticket', function () {
    [, , $status] = makeSupportNudgeAndPendingStatus();
    $other = User::factory()->role(UserRole::Support)->create();

    Ticket::factory()->create(['created_by' => $other->id, 'created_at' => now()]);

    Artisan::call('app:run-team-nudge-auto-detect');

    expect($status->fresh()->status)->toBe(NudgeStatus::Pending);
});

it('does not auto-clear from a ticket logged before the current period started', function () {
    [, $user, $status] = makeSupportNudgeAndPendingStatus();

    Ticket::factory()->create([
        'created_by' => $user->id,
        'created_at' => TeamNudge::currentPeriodStart()->copy()->subWeek(),
    ]);

    Artisan::call('app:run-team-nudge-auto-detect');

    expect($status->fresh()->status)->toBe(NudgeStatus::Pending);
});

it('leaves an already-done row untouched (does not re-check it)', function () {
    [, $user, $status] = makeSupportNudgeAndPendingStatus();
    $status->update(['status' => NudgeStatus::Done->value, 'completed_via' => 'manual', 'completed_at' => now()]);

    Artisan::call('app:run-team-nudge-auto-detect');

    expect($status->fresh()->completed_via)->toBe('manual');
});

it('ignores a nudge with no auto_detect_type', function () {
    $nudge = TeamNudge::factory()->create([
        'target_role' => UserRole::Sales->value,
        'recurrence' => NudgeRecurrence::OneTime->value,
        'auto_detect_type' => null,
    ]);
    $user = User::factory()->role(UserRole::Sales)->create();
    $status = TeamNudgeStatus::factory()->create([
        'team_nudge_id' => $nudge->id,
        'user_id' => $user->id,
        'period_start' => null,
        'status' => NudgeStatus::Pending->value,
    ]);

    Artisan::call('app:run-team-nudge-auto-detect');

    expect($status->fresh()->status)->toBe(NudgeStatus::Pending);
});
