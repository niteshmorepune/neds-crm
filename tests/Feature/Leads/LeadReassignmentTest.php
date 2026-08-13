<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadReassignedNotification;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a Sales owner hand their lead off to another active Sales peer', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $peer = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($owner)->post(route('leads.reassign', $lead), [
        'to_user_id' => $peer->id,
        'reason' => 'on_leave',
    ])->assertRedirect(route('leads.show', $lead));

    expect($lead->fresh()->owner_id)->toBe($peer->id);
    Notification::assertSentTo($peer, LeadReassignedNotification::class);
});

it('appends a visible note explaining the reassignment', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $peer = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($owner)->post(route('leads.reassign', $lead), [
        'to_user_id' => $peer->id,
        'reason' => 'left_company',
    ]);

    $note = $lead->fresh()->notes()->latest()->first();
    expect($note)->not->toBeNull()
        ->and($note->user_id)->toBe($owner->id)
        ->and($note->body)->toContain($owner->name)
        ->and($note->body)->toContain($peer->name)
        ->and($note->body)->toContain('Left the company');
});

it('forbids a Sales user from reassigning a lead they do not own', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $bystander = User::factory()->role(UserRole::Sales)->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($bystander)->post(route('leads.reassign', $lead), [
        'to_user_id' => $target->id,
        'reason' => 'rebalancing',
    ])->assertForbidden();

    expect($lead->fresh()->owner_id)->toBe($owner->id);
});

it('blocks a Sales user from handing a lead to Admin/Manager or an inactive peer', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $inactivePeer = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($owner)->post(route('leads.reassign', $lead), [
        'to_user_id' => $manager->id, 'reason' => 'other',
    ])->assertSessionHasErrors('to_user_id');

    $this->actingAs($owner)->post(route('leads.reassign', $lead), [
        'to_user_id' => $inactivePeer->id, 'reason' => 'other',
    ])->assertSessionHasErrors('to_user_id');

    expect($lead->fresh()->owner_id)->toBe($owner->id);
});

it('blocks a Sales user from reassigning a lead to themselves', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($owner)->post(route('leads.reassign', $lead), [
        'to_user_id' => $owner->id, 'reason' => 'other',
    ])->assertSessionHasErrors('to_user_id');
});

it('lets a manager reassign any lead to any active Sales/Manager/Admin user', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $anotherManager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($manager)->post(route('leads.reassign', $lead), [
        'to_user_id' => $anotherManager->id, 'reason' => 'rebalancing',
    ])->assertRedirect();

    expect($lead->fresh()->owner_id)->toBe($anotherManager->id);
});

it('forbids support/accounts from reaching the reassign route at all', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $target = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($support)->post(route('leads.reassign', $lead), [
        'to_user_id' => $target->id, 'reason' => 'other',
    ])->assertForbidden();
});
