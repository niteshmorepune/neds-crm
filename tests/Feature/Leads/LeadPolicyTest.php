<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets sales see only their own or unowned leads, not another Sales rep\'s', function () {
    // Real incident 2026-09-03: Kiran and Mohit (both Sales) could see each
    // other's leads under the old "everyone sees everything" rule.
    //
    // $unassigned created BEFORE any Sales user exists, so LeadObserver's
    // round-robin auto-assign has nobody to claim it for yet — otherwise it
    // would immediately assign this to $sales, defeating the point of
    // testing the "or unowned" branch of scopeVisibleTo.
    $unassigned = Lead::factory()->create(['owner_id' => null]);

    $sales = User::factory()->role(UserRole::Sales)->create();
    $own = Lead::factory()->ownedBy($sales->id)->create();
    $foreign = Lead::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    $visibleIds = Lead::visibleTo($sales)->pluck('id');
    expect($visibleIds)->toContain($own->id)
        ->toContain($unassigned->id)
        ->not->toContain($foreign->id);

    expect($sales->can('view', $own))->toBeTrue()
        ->and($sales->can('view', $unassigned))->toBeTrue()
        ->and($sales->can('view', $foreign))->toBeFalse();

    $this->actingAs($sales)->get(route('leads.show', $own))->assertOk();
    $this->actingAs($sales)->get(route('leads.show', $foreign))->assertForbidden();
});

it('denies support and accounts access when menu is not granted', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    // menu.access:lead-generation blocks the route for roles without access.
    $this->actingAs($user)->get(route('leads.index'))->assertForbidden();
})->with([
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
]);

it('lets telecaller view and update only leads assigned to them via telecaller_id, not every lead', function () {
    // Reverses the old "shared calling queue, no ownership" 2026-07-26
    // decision — real per-telecaller assignment shipped 2026-09-03, same
    // day as the Sales-visibility fix above.
    //
    // Created BEFORE $telecaller exists, so LeadObserver's round-robin
    // auto-assign has no active Telecaller to hand these to yet — otherwise
    // it would assign them to $telecaller itself, defeating the test.
    $notMine = Lead::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();
    $unassigned = Lead::factory()->create(['owner_id' => null]);

    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $mine = Lead::factory()->create(['telecaller_id' => $telecaller->id]);

    $this->actingAs($telecaller)->get(route('leads.index'))->assertOk();

    expect($telecaller->can('view', $mine))->toBeTrue()
        ->and($telecaller->can('view', $notMine))->toBeFalse()
        ->and($telecaller->can('view', $unassigned))->toBeFalse()
        ->and($telecaller->can('update', $mine))->toBeTrue()
        ->and($telecaller->can('update', $notMine))->toBeFalse();

    expect(Lead::visibleTo($telecaller)->pluck('id'))
        ->toContain($mine->id)
        ->not->toContain($notMine->id)
        ->not->toContain($unassigned->id);
});

it('widens a multi-role Sales+Telecaller user to the union of both scopes, never narrows', function () {
    // $neither created BEFORE $user exists as an active Telecaller, so the
    // round-robin auto-assign has nobody to hand it to yet.
    $neither = Lead::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    $user = User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Telecaller)->create();
    $ownedBySelf = Lead::factory()->ownedBy($user->id)->create();
    $telecalledBySelf = Lead::factory()->create(['telecaller_id' => $user->id]);

    $visibleIds = Lead::visibleTo($user)->pluck('id');
    expect($visibleIds)->toContain($ownedBySelf->id)
        ->toContain($telecalledBySelf->id)
        ->not->toContain($neither->id);
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

it('export: is Admin-only, deliberately excluding Manager unlike every other capability here', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    expect($user->can('export', Lead::class))->toBeFalse();
})->with([
    'manager' => UserRole::Manager,
    'sales' => UserRole::Sales,
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
    'intern' => UserRole::Intern,
    'telecaller' => UserRole::Telecaller,
]);

it('export: allows Admin, whether held as the primary role or an additional one', function () {
    $primaryAdmin = User::factory()->role(UserRole::Admin)->create();
    $additionalAdmin = User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Admin)->create();

    expect($primaryAdmin->can('export', Lead::class))->toBeTrue()
        ->and($additionalAdmin->can('export', Lead::class))->toBeTrue();
});
