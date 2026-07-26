<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets sales see all leads, not just their own', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $own = Lead::factory()->ownedBy($sales->id)->create();
    $unassigned = Lead::factory()->create(['owner_id' => null]);
    $foreign = Lead::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    // All roles now see all leads — no owner-based restriction.
    expect(Lead::visibleTo($sales)->pluck('id'))
        ->toContain($own->id)
        ->toContain($unassigned->id)
        ->toContain($foreign->id);

    expect($sales->can('view', $foreign))->toBeTrue();
    $this->actingAs($sales)->get(route('leads.show', $foreign))->assertOk();
});

it('denies support and accounts access when menu is not granted', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    // menu.access:lead-generation blocks the route for roles without access.
    $this->actingAs($user)->get(route('leads.index'))->assertForbidden();
})->with([
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
]);

it('lets telecaller view and update any lead (a shared calling queue, not an owned pipeline)', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $ownedBySales = Lead::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();
    $unassigned = Lead::factory()->create(['owner_id' => null]);

    $this->actingAs($telecaller)->get(route('leads.index'))->assertOk();

    expect($telecaller->can('view', $ownedBySales))->toBeTrue()
        ->and($telecaller->can('view', $unassigned))->toBeTrue()
        ->and($telecaller->can('update', $ownedBySales))->toBeTrue()
        ->and($telecaller->can('update', $unassigned))->toBeTrue();
});

it('does not let telecaller create, convert, or delete leads', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $lead = Lead::factory()->create();

    expect($telecaller->can('create', Lead::class))->toBeFalse()
        ->and($telecaller->can('convert', $lead))->toBeFalse()
        ->and($telecaller->can('delete', $lead))->toBeFalse();
});

it('lets managers and admins see all leads', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $foreign = Lead::factory()->ownedBy(User::factory()->create()->id)->create();

    expect(Lead::visibleTo($manager)->pluck('id'))->toContain($foreign->id)
        ->and($manager->can('view', $foreign))->toBeTrue();
});

it('manageMeetings: is its own check, not tied to update() — a non-owning sales rep can still manage meetings', function () {
    // 2026-07-25 regression: MeetingImport originally reused update() (owning
    // Sales rep or Admin/Manager only), which would have blocked Support AND
    // any non-owning Sales rep from Create Meeting — the opposite of the
    // shared-connection feature's whole point.
    $owner = User::factory()->role(UserRole::Sales)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();
    $support = User::factory()->role(UserRole::Support)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    expect($otherSales->can('update', $lead))->toBeFalse()
        ->and($otherSales->can('manageMeetings', $lead))->toBeTrue()
        ->and($support->can('manageMeetings', $lead))->toBeTrue();
});

it('manageMeetings: excludes accounts, intern, and telecaller', function (UserRole $role) {
    $lead = Lead::factory()->create();
    $user = User::factory()->role($role)->create();

    expect($user->can('manageMeetings', $lead))->toBeFalse();
})->with([
    'accounts' => UserRole::Accounts,
    'intern' => UserRole::Intern,
    'telecaller' => UserRole::Telecaller,
]);
