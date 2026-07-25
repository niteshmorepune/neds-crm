<?php

use App\Enums\NudgeRecurrence;
use App\Enums\NudgeStatus;
use App\Enums\UserRole;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('creates a pending status row for the current period for every active targeted user', function () {
    $nudge = TeamNudge::factory()->create([
        'target_role' => UserRole::Support->value,
        'recurrence' => NudgeRecurrence::Weekly->value,
    ]);
    $support1 = User::factory()->role(UserRole::Support)->create(['is_active' => true]);
    $support2 = User::factory()->role(UserRole::Support)->create(['is_active' => true]);
    $inactiveSupport = User::factory()->role(UserRole::Support)->create(['is_active' => false]);
    $sales = User::factory()->role(UserRole::Sales)->create(['is_active' => true]);

    Artisan::call('app:rollover-team-nudges');

    $userIds = TeamNudgeStatus::where('team_nudge_id', $nudge->id)
        ->where('period_start', TeamNudge::currentPeriodStart())
        ->pluck('user_id');

    expect($userIds)->toContain($support1->id)->toContain($support2->id)
        ->not->toContain($inactiveSupport->id)
        ->not->toContain($sales->id);
});

it('does not touch one-time nudges', function () {
    $oneTime = TeamNudge::factory()->create(['recurrence' => NudgeRecurrence::OneTime->value]);
    User::factory()->role(UserRole::Sales)->create();

    Artisan::call('app:rollover-team-nudges');

    expect(TeamNudgeStatus::where('team_nudge_id', $oneTime->id)->count())->toBe(0);
});

it('running rollover twice for the same week does not duplicate rows', function () {
    $nudge = TeamNudge::factory()->create([
        'target_role' => UserRole::Support->value,
        'recurrence' => NudgeRecurrence::Weekly->value,
    ]);
    User::factory()->role(UserRole::Support)->create(['is_active' => true]);

    Artisan::call('app:rollover-team-nudges');
    Artisan::call('app:rollover-team-nudges');

    expect(TeamNudgeStatus::where('team_nudge_id', $nudge->id)->count())->toBe(1);
});

it('a new week rollover creates a fresh pending row without carrying forward last week\'s completed status', function () {
    $nudge = TeamNudge::factory()->create([
        'target_role' => UserRole::Support->value,
        'recurrence' => NudgeRecurrence::Weekly->value,
    ]);
    $user = User::factory()->role(UserRole::Support)->create(['is_active' => true]);

    // Last week's row was already marked done.
    $lastWeek = TeamNudgeStatus::factory()->create([
        'team_nudge_id' => $nudge->id,
        'user_id' => $user->id,
        'period_start' => TeamNudge::currentPeriodStart()->copy()->subWeek(),
        'status' => NudgeStatus::Done->value,
        'completed_via' => 'auto',
        'completed_at' => now()->subWeek(),
    ]);

    Artisan::call('app:rollover-team-nudges');

    $thisWeek = TeamNudgeStatus::where('team_nudge_id', $nudge->id)
        ->where('period_start', TeamNudge::currentPeriodStart())
        ->first();

    expect($thisWeek)->not->toBeNull()
        ->and($thisWeek->id)->not->toBe($lastWeek->id)
        ->and($thisWeek->status)->toBe(NudgeStatus::Pending)
        ->and($lastWeek->fresh()->status)->toBe(NudgeStatus::Done);
});
