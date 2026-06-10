<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('limits a sales user to their own and unassigned clients', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();

    $own = Customer::factory()->ownedBy($sales->id)->create();
    $unassigned = Customer::factory()->create(['owner_id' => null]);
    $foreign = Customer::factory()->ownedBy($other->id)->create();

    $visible = Customer::visibleTo($sales)->pluck('id');

    expect($visible)->toContain($own->id)
        ->toContain($unassigned->id)
        ->not->toContain($foreign->id);

    expect($sales->can('view', $own))->toBeTrue()
        ->and($sales->can('view', $unassigned))->toBeTrue()
        ->and($sales->can('view', $foreign))->toBeFalse();
});

it('lets managers, admins, support and accounts see all clients', function (UserRole $role) {
    $user = User::factory()->role($role)->create();
    $foreign = Customer::factory()->ownedBy(User::factory()->create()->id)->create();

    expect(Customer::visibleTo($user)->pluck('id'))->toContain($foreign->id)
        ->and($user->can('view', $foreign))->toBeTrue();
})->with([
    'manager' => UserRole::Manager,
    'admin' => UserRole::Admin,
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
]);

it('returns 403 when sales opens another rep\'s client', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $foreign = Customer::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    $this->actingAs($sales)->get(route('clients.show', $foreign))->assertForbidden();
});

it('only allows admin/manager or the owning sales rep to delete', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $client = Customer::factory()->ownedBy($owner->id)->create();
    $otherSales = User::factory()->role(UserRole::Sales)->create();

    expect($owner->can('delete', $client))->toBeTrue()
        ->and($otherSales->can('delete', $client))->toBeFalse()
        ->and(User::factory()->role(UserRole::Manager)->create()->can('delete', $client))->toBeTrue();
});
