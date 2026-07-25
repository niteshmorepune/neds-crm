<?php

use App\Enums\NudgeRecurrence;
use App\Enums\UserRole;
use App\Models\TeamNudge;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager view, but forbids a sales user, from the Team Nudges management screen', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('team-nudges.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('team-nudges.index'))->assertForbidden();
});

it('renders the create and edit pages for an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $nudge = TeamNudge::factory()->create();

    $this->actingAs($admin)->get(route('team-nudges.create'))->assertOk();
    $this->actingAs($admin)->get(route('team-nudges.edit', $nudge))->assertOk();
});

it('creates a new nudge and stamps the creating user', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('team-nudges.store'), [
        'title' => 'Record staff training videos',
        'description' => 'Scripts are ready.',
        'target_role' => UserRole::Admin->value,
        'recurrence' => NudgeRecurrence::OneTime->value,
        'is_active' => '1',
    ])->assertRedirect(route('team-nudges.index'));

    $nudge = TeamNudge::firstWhere('title', 'Record staff training videos');
    expect($nudge)->not->toBeNull()
        ->and($nudge->created_by)->toBe($admin->id)
        ->and($nudge->target_role)->toBe(UserRole::Admin);
});

it('rejects an auto_detect_type on a one-time nudge', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('team-nudges.store'), [
        'title' => 'Bad combo',
        'recurrence' => NudgeRecurrence::OneTime->value,
        'auto_detect_type' => 'tickets_logged_this_week',
    ])->assertSessionHasErrors('auto_detect_type');
});

it('accepts an auto_detect_type on a weekly nudge', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('team-nudges.store'), [
        'title' => 'Good combo',
        'recurrence' => NudgeRecurrence::Weekly->value,
        'auto_detect_type' => 'tickets_logged_this_week',
    ])->assertRedirect(route('team-nudges.index'));

    expect(TeamNudge::firstWhere('title', 'Good combo'))->not->toBeNull();
});

it('updates a nudge', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $nudge = TeamNudge::factory()->create(['title' => 'Old title', 'recurrence' => NudgeRecurrence::OneTime->value]);

    $this->actingAs($admin)->put(route('team-nudges.update', $nudge), [
        'title' => 'New title',
        'recurrence' => NudgeRecurrence::OneTime->value,
        'is_active' => '1',
    ])->assertRedirect(route('team-nudges.index'));

    expect($nudge->fresh()->title)->toBe('New title');
});

it('deletes a nudge', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $nudge = TeamNudge::factory()->create();

    $this->actingAs($admin)->delete(route('team-nudges.destroy', $nudge))->assertRedirect();

    expect(TeamNudge::find($nudge->id))->toBeNull();
});

it('forbids a sales user from creating, editing or deleting a nudge', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $nudge = TeamNudge::factory()->create();

    $this->actingAs($sales)->post(route('team-nudges.store'), ['title' => 'Sneaky'])->assertForbidden();
    $this->actingAs($sales)->put(route('team-nudges.update', $nudge), ['title' => 'Sneaky'])->assertForbidden();
    $this->actingAs($sales)->delete(route('team-nudges.destroy', $nudge))->assertForbidden();
});
