<?php

use App\Enums\LeadReassignmentReason;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadReassignment;
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

it('writes a structured LeadReassignment log row for the Reassignment Analytics report', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $peer = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    $this->actingAs($owner)->post(route('leads.reassign', $lead), [
        'to_user_id' => $peer->id,
        'reason' => 'on_leave',
    ]);

    $log = LeadReassignment::where('lead_id', $lead->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->from_user_id)->toBe($owner->id)
        ->and($log->to_user_id)->toBe($peer->id)
        ->and($log->reassigned_by)->toBe($owner->id)
        ->and($log->reason)->toBe(LeadReassignmentReason::OnLeave)
        ->and($log->created_at)->not->toBeNull();
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

it('lets a manager bulk-reassign all of one Sales user\'s open leads to another in one action', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $kiran = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    $mohit = User::factory()->role(UserRole::Sales)->create(['name' => 'Mohit Patil']);
    $open = Lead::factory()->count(3)->ownedBy($kiran->id)->create(['status' => LeadStatus::New]);
    $closed = Lead::factory()->ownedBy($kiran->id)->create(['status' => LeadStatus::Lost]);

    $this->actingAs($manager)->post(route('leads.bulk-reassign'), [
        'from_user_id' => $kiran->id,
        'to_user_id' => $mohit->id,
        'reason' => 'on_leave',
    ])->assertRedirect(route('leads.index', ['owner_id' => $kiran->id]));

    foreach ($open as $lead) {
        expect($lead->fresh()->owner_id)->toBe($mohit->id);
    }
    expect($closed->fresh()->owner_id)->toBe($kiran->id); // closed lead untouched
    Notification::assertSentTimes(LeadReassignedNotification::class, 3);
});

it('accepts a bulk-reassign target whose SECONDARY role is Sales, even if their primary role is not — 2026-08-13 regression', function () {
    // Real incident: Mohit's primary role is Support with Sales as an
    // additional role. The dropdown (built via withAnyRole()) correctly
    // offered him, but the validation rule originally checked only the raw
    // `role` column — every attempt silently failed with no visible error.
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $kiran = User::factory()->role(UserRole::Sales)->create();
    $mohit = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($kiran->id)->create(['status' => LeadStatus::New]);

    $this->actingAs($manager)->post(route('leads.bulk-reassign'), [
        'from_user_id' => $kiran->id,
        'to_user_id' => $mohit->id,
        'reason' => 'on_leave',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($lead->fresh()->owner_id)->toBe($mohit->id);
});

it('forbids a Sales user from bulk-reassigning someone else\'s leads', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $kiran = User::factory()->role(UserRole::Sales)->create();
    $mohit = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($kiran->id)->create();

    $this->actingAs($sales)->post(route('leads.bulk-reassign'), [
        'from_user_id' => $kiran->id, 'to_user_id' => $mohit->id, 'reason' => 'on_leave',
    ])->assertForbidden();
});

it('rejects a bulk-reassign target that is inactive or not a valid owner role', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $kiran = User::factory()->role(UserRole::Sales)->create();
    $inactive = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($manager)->post(route('leads.bulk-reassign'), [
        'from_user_id' => $kiran->id, 'to_user_id' => $inactive->id, 'reason' => 'on_leave',
    ])->assertSessionHasErrors('to_user_id');

    $this->actingAs($manager)->post(route('leads.bulk-reassign'), [
        'from_user_id' => $kiran->id, 'to_user_id' => $support->id, 'reason' => 'on_leave',
    ])->assertSessionHasErrors('to_user_id');
});

it('shows the bulk-reassign panel on the Lead Generation list when filtered to a single owner, admin/manager only', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $kiran = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    Lead::factory()->ownedBy($kiran->id)->create(['status' => LeadStatus::New]);

    $this->actingAs($manager)->get(route('leads.index', ['owner_id' => $kiran->id]))
        ->assertOk()
        ->assertSee('Reassign All');

    $this->actingAs($sales)->get(route('leads.index', ['owner_id' => $kiran->id]))
        ->assertOk()
        ->assertDontSee('Reassign All');
});
