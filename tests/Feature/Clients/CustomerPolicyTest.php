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

it('lets support manage (add/edit/delete) contacts, unlike accounts who can only view them', function () {
    $client = Customer::factory()->create();
    $support = User::factory()->role(UserRole::Support)->create();
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    expect($support->can('manage', $client))->toBeTrue()
        ->and($accounts->can('manage', $client))->toBeFalse();
});
