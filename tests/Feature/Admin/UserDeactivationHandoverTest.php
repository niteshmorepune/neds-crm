<?php

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
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('blocks deactivating a Sales user with open leads unless a handover target is chosen', function () {
    $departing = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->count(2)->ownedBy($departing->id)->create(['status' => LeadStatus::New]);

    $this->actingAs($this->admin)->put(route('users.update', $departing), [
        'name' => $departing->name,
        'email' => $departing->email,
        'role' => UserRole::Sales->value,
        'is_active' => '0',
    ])->assertSessionHasErrors('reassign_leads_to');

    expect($departing->fresh()->is_active)->toBeTrue();
});

it('bulk-reassigns open leads to the chosen Sales user when deactivating', function () {
    Notification::fake();
    $departing = User::factory()->role(UserRole::Sales)->create();
    $successor = User::factory()->role(UserRole::Sales)->create();
    $open = Lead::factory()->count(2)->ownedBy($departing->id)->create(['status' => LeadStatus::New]);
    $closed = Lead::factory()->ownedBy($departing->id)->create(['status' => LeadStatus::Converted]);

    $this->actingAs($this->admin)->put(route('users.update', $departing), [
        'name' => $departing->name,
        'email' => $departing->email,
        'role' => UserRole::Sales->value,
        'is_active' => '0',
        'reassign_leads_to' => $successor->id,
        'reassign_reason' => 'left_company',
    ])->assertRedirect(route('users.index'));

    expect($departing->fresh()->is_active)->toBeFalse();

    foreach ($open as $lead) {
        expect($lead->fresh()->owner_id)->toBe($successor->id);
    }
    // A closed (converted) lead isn't part of the departing rep's active book — left untouched.
    expect($closed->fresh()->owner_id)->toBe($departing->id);

    Notification::assertSentTo($successor, LeadReassignedNotification::class);

    // The bulk handover reuses ReassignLead per lead, so each open lead also
    // gets its own structured LeadReassignment log row (Reassignment
    // Analytics report) — not just the single Note-based trail.
    expect(LeadReassignment::where('to_user_id', $successor->id)->count())->toBe(2);
});

it('does not require a handover target when deactivating a user with no open leads', function () {
    $staff = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'role' => UserRole::Sales->value,
        'is_active' => '0',
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    expect($staff->fresh()->is_active)->toBeFalse();
});

it('does not trigger the handover flow when a user is edited without changing active status', function () {
    $staff = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($staff->id)->create(['status' => LeadStatus::New]);

    $this->actingAs($this->admin)->put(route('users.update', $staff), [
        'name' => 'Renamed Only',
        'email' => $staff->email,
        'role' => UserRole::Sales->value,
        'is_active' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    expect($staff->fresh()->is_active)->toBeTrue();
});
