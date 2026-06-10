<?php

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('limits sales to their own and unassigned leads', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $own = Lead::factory()->ownedBy($sales->id)->create();
    $unassigned = Lead::factory()->create(['owner_id' => null]);
    $foreign = Lead::factory()->ownedBy(User::factory()->role(UserRole::Sales)->create()->id)->create();

    expect(Lead::visibleTo($sales)->pluck('id'))
        ->toContain($own->id)->toContain($unassigned->id)->not->toContain($foreign->id);

    expect($sales->can('view', $foreign))->toBeFalse();
    $this->actingAs($sales)->get(route('leads.show', $foreign))->assertForbidden();
});

it('denies support and accounts any lead access', function (UserRole $role) {
    $user = User::factory()->role($role)->create();

    expect($user->can('viewAny', Lead::class))->toBeFalse();
    // menu.access:lead-generation also blocks the route for these roles.
    $this->actingAs($user)->get(route('leads.index'))->assertForbidden();
})->with([
    'support' => UserRole::Support,
    'accounts' => UserRole::Accounts,
]);

it('lets managers and admins see all leads', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $foreign = Lead::factory()->ownedBy(User::factory()->create()->id)->create();

    expect(Lead::visibleTo($manager)->pluck('id'))->toContain($foreign->id)
        ->and($manager->can('view', $foreign))->toBeTrue();
});
