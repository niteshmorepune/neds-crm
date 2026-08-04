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

it('limits a support user to their own and unassigned clients once Sales is granted as an additional role', function () {
    // Mirrors CustomerPolicy::view's existing "if hasRole(Sales) at all,
    // restrict" priority — an additional Sales role now reaches that branch
    // even though the primary role (Support) would otherwise see everything.
    $supportPlusSales = User::factory()->role(UserRole::Support)->withAdditionalRoles(UserRole::Sales)->create();
    $ownClient = Customer::factory()->ownedBy($supportPlusSales->id)->create();
    $foreignClient = Customer::factory()->ownedBy(User::factory()->create()->id)->create();

    $visible = Customer::visibleTo($supportPlusSales)->pluck('id');

    expect($visible)->toContain($ownClient->id)
        ->and($visible)->not->toContain($foreignClient->id);
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
