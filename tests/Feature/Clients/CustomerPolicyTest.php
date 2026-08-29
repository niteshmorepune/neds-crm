<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets admin, manager, support and accounts see all clients', function (UserRole $role) {
    $user = User::factory()->role($role)->create();
    $foreign = Customer::factory()->ownedBy(User::factory()->create()->id)->create();

    expect(Customer::visibleTo($user)->pluck('id'))->toContain($foreign->id)
        ->and($user->can('view', $foreign))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
    'telecaller' => UserRole::Telecaller,
]);

it('limits a sales rep to their own and unassigned clients', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();

    $ownClient = Customer::factory()->ownedBy($sales->id)->create();
    $unassignedClient = Customer::factory()->create(['owner_id' => null]);
    $foreignClient = Customer::factory()->ownedBy($other->id)->create();

    $visible = Customer::visibleTo($sales)->pluck('id');

    expect($visible)->toContain($ownClient->id)
        ->and($visible)->toContain($unassignedClient->id)
        ->and($visible)->not->toContain($foreignClient->id);

    expect($sales->can('view', $ownClient))->toBeTrue()
        ->and($sales->can('view', $unassignedClient))->toBeTrue()
        ->and($sales->can('view', $foreignClient))->toBeFalse();
});

it('still sees every client when Support is the primary role and Sales is only an additional role', function () {
    // 2026-08-29 fix: an additional role must only ever WIDEN access, never
    // narrow it below what any one of the user's roles independently grants
    // (real case: Mohit Patil, primary Sales + additional Support, was
    // seeing zero clients — Sales's owned-or-unassigned narrowing was
    // wrongly vetoing Support's full-access grant).
    $supportPlusSales = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();
    $foreignClient = Customer::factory()->ownedBy(User::factory()->create()->id)->create();

    $visible = Customer::visibleTo($supportPlusSales)->pluck('id');

    expect($visible)->toContain($foreignClient->id)
        ->and($supportPlusSales->can('view', $foreignClient))->toBeTrue();
});

it('still sees and edits every client when Sales is primary and Support is only an additional role', function () {
    // Same fix, the other direction (Mohit Patil's actual real-world setup):
    // Sales primary + Support additional must see everything (Support's
    // full-access grant), AND keep editing their own clients (Sales's own
    // grant must not be vetoed by the Intern/Support/Telecaller deny-list
    // in update()).
    $salesPlusSupport = User::factory()->role(UserRole::Sales)->withAdditionalRoles(UserRole::Support)->create();
    $ownClient = Customer::factory()->ownedBy($salesPlusSupport->id)->create();
    $foreignClient = Customer::factory()->ownedBy(User::factory()->create()->id)->create();

    expect(Customer::visibleTo($salesPlusSupport)->pluck('id'))->toContain($foreignClient->id)
        ->and($salesPlusSupport->can('view', $foreignClient))->toBeTrue()
        ->and($salesPlusSupport->can('update', $ownClient))->toBeTrue();
});

it('only allows admin/manager or the owning sales rep to delete', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $client = Customer::factory()->ownedBy($owner->id)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();

    expect($owner->can('delete', $client))->toBeTrue()
        ->and($otherSales->can('delete', $client))->toBeFalse()
        ->and(User::factory()->role(UserRole::Manager)->create()->can('delete', $client))->toBeTrue();
});

it('lets support view but never edit client profiles or manage contacts', function () {
    $client = Customer::factory()->create();
    $support = User::factory()->role(UserRole::Support)->create();

    expect($support->can('view', $client))->toBeTrue()
        ->and($support->can('update', $client))->toBeFalse()
        ->and($support->can('manage', $client))->toBeFalse();
});

it('lets telecaller view but never edit client profiles or manage contacts', function () {
    // Telecaller's scope is leads + calling only — clients aren't part of it.
    // CustomerPolicy::update() is a deny-list (defaults to true for anyone
    // non-Sales), so telecaller must be explicitly listed there or it would
    // silently inherit full client-edit rights.
    $client = Customer::factory()->create();
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();

    expect($telecaller->can('view', $client))->toBeTrue()
        ->and($telecaller->can('update', $client))->toBeFalse()
        ->and($telecaller->can('manage', $client))->toBeFalse();
});

it('confirms accounts cannot manage (add/edit/delete) contacts either, only view them', function () {
    $client = Customer::factory()->create();
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    expect($accounts->can('manage', $client))->toBeFalse();
});

it('manageMeetings: lets support create/import Google Meet notes even though they cannot manage() contacts', function () {
    // 2026-07-25 regression: MeetingImport originally reused manage() (which
    // deliberately excludes Support for contacts), silently blocking Support
    // from Create Meeting too — the opposite of the shared-connection
    // feature's whole point. manageMeetings() is its own check, mirroring
    // Calling's role set instead.
    $client = Customer::factory()->create();
    $support = User::factory()->role(UserRole::Support)->create();

    expect($support->can('manage', $client))->toBeFalse()
        ->and($support->can('manageMeetings', $client))->toBeTrue();
});

it('manageMeetings: lets any Sales rep manage meetings on any client, not just their own', function (UserRole $role) {
    $client = Customer::factory()->ownedBy(User::factory()->create()->id)->create();
    $user = User::factory()->role($role)->create();

    expect($user->can('manageMeetings', $client))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
    'sales (non-owning)' => UserRole::Sales,
    'support' => UserRole::Support,
]);

it('manageMeetings: excludes accounts, intern, and telecaller', function (UserRole $role) {
    $client = Customer::factory()->create();
    $user = User::factory()->role($role)->create();

    expect($user->can('manageMeetings', $client))->toBeFalse();
})->with([
    'accounts' => UserRole::Accounts,
    'intern' => UserRole::Intern,
    'telecaller' => UserRole::Telecaller,
]);

it('manageLinks: lets support create/edit/delete important links even though they cannot manage() contacts', function () {
    // Same shape as the manageMeetings regression above — Links is a shared
    // client resource, not sales-owned relationship data, so it's its own
    // check rather than reusing manage() (which deliberately excludes
    // Support for contacts/notes).
    $client = Customer::factory()->create();
    $support = User::factory()->role(UserRole::Support)->create();

    expect($support->can('manage', $client))->toBeFalse()
        ->and($support->can('manageLinks', $client))->toBeTrue();
});

it('manageLinks: lets any Sales rep manage links on any client, not just their own', function (UserRole $role) {
    $client = Customer::factory()->ownedBy(User::factory()->create()->id)->create();
    $user = User::factory()->role($role)->create();

    expect($user->can('manageLinks', $client))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
    'sales (non-owning)' => UserRole::Sales,
    'support' => UserRole::Support,
]);

it('manageLinks: excludes accounts, intern, and telecaller', function (UserRole $role) {
    $client = Customer::factory()->create();
    $user = User::factory()->role($role)->create();

    expect($user->can('manageLinks', $client))->toBeFalse();
})->with([
    'accounts' => UserRole::Accounts,
    'intern' => UserRole::Intern,
    'telecaller' => UserRole::Telecaller,
]);
