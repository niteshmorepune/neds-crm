<?php

use App\Enums\NudgeStatus;
use App\Enums\UserRole;
use App\Livewire\MyTeamNudges;
use App\Models\TeamNudge;
use App\Models\TeamNudgeStatus;
use App\Models\User;
use Livewire\Livewire;

it('shows a nudge targeted at the viewer\'s role', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    TeamNudge::factory()->create(['title' => 'Route issues through Tickets', 'target_role' => UserRole::Support->value]);

    Livewire::actingAs($support)->test(MyTeamNudges::class)
        ->assertSee('Route issues through Tickets');
});

it('does not show a nudge targeted at a different role', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    TeamNudge::factory()->create(['title' => 'Route issues through Tickets', 'target_role' => UserRole::Support->value]);

    Livewire::actingAs($sales)->test(MyTeamNudges::class)
        ->assertDontSee('Route issues through Tickets');
});

it('marking a nudge done removes it from view and stamps manual completion', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    TeamNudge::factory()->create(['title' => 'Log deals', 'target_role' => UserRole::Sales->value]);

    $component = Livewire::actingAs($sales)->test(MyTeamNudges::class)
        ->assertSee('Log deals');

    $status = TeamNudgeStatus::where('user_id', $sales->id)->firstOrFail();

    $component->call('markDone', $status->id)->assertDontSee('Log deals');

    expect($status->fresh()->status)->toBe(NudgeStatus::Done)
        ->and($status->fresh()->completed_via)->toBe('manual');
});

it('snoozing hides the nudge from the viewer for the snooze window', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    TeamNudge::factory()->create(['title' => 'Log deals', 'target_role' => UserRole::Sales->value]);

    $component = Livewire::actingAs($sales)->test(MyTeamNudges::class)->assertSee('Log deals');
    $status = TeamNudgeStatus::where('user_id', $sales->id)->firstOrFail();

    $component->call('snooze', $status->id)->assertDontSee('Log deals');

    expect($status->fresh()->status)->toBe(NudgeStatus::Snoozed);
});

it('forbids marking someone else\'s nudge status as done', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $other = User::factory()->role(UserRole::Support)->create();
    $status = TeamNudgeStatus::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($support)->test(MyTeamNudges::class)
        ->call('markDone', $status->id)
        ->assertStatus(403);

    expect($status->fresh()->status)->toBe(NudgeStatus::Pending);
});
