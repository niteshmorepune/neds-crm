<?php

use App\Enums\NudgeRecurrence;
use App\Enums\NudgeStatus;
use App\Enums\UserRole;
use App\Livewire\TeamNudgeOverview;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

it('shows a snoozed user as Snoozed, not folded into the done count', function () {
    $nudge = TeamNudge::factory()->create([
        'target_role' => UserRole::Support->value,
        'recurrence' => NudgeRecurrence::Weekly->value,
    ]);
    $snoozedUser = User::factory()->role(UserRole::Support)->create(['name' => 'Snoozy']);
    $doneUser = User::factory()->role(UserRole::Support)->create(['name' => 'Doer']);

    TeamNudgeStatus::factory()->create([
        'team_nudge_id' => $nudge->id,
        'user_id' => $snoozedUser->id,
        'period_start' => TeamNudge::currentPeriodStart(),
        'status' => NudgeStatus::Snoozed->value,
        'snoozed_until' => now()->addDays(2),
    ]);
    TeamNudgeStatus::factory()->create([
        'team_nudge_id' => $nudge->id,
        'user_id' => $doneUser->id,
        'period_start' => TeamNudge::currentPeriodStart(),
        'status' => NudgeStatus::Done->value,
        'completed_via' => 'manual',
        'completed_at' => now(),
    ]);

    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)->test(TeamNudgeOverview::class)
        ->assertSee('Snoozy')
        ->assertSee('Snoozed')
        ->assertSee('1/2 done'); // snooze must not count as done
});

it('shows a user with no status row yet as Pending, not missing', function () {
    TeamNudge::factory()->create([
        'target_role' => UserRole::Support->value,
        'recurrence' => NudgeRecurrence::Weekly->value,
    ]);
    $neverVisited = User::factory()->role(UserRole::Support)->create(['name' => 'NeverOpened']);
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)->test(TeamNudgeOverview::class)
        ->assertSee('NeverOpened')
        ->assertSee('Pending');
});

it('renders the Team Nudges management page for a manager, including the overview', function () {
    $this->seed(MenuItemsSeeder::class);
    TeamNudge::factory()->create(['title' => 'Sample nudge', 'target_role' => UserRole::Support->value]);
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('team-nudges.index'))
        ->assertOk()
        ->assertSee('Sample nudge')
        ->assertSee('Team completion overview');
});
