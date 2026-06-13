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

it('lets managers and admins see all leads', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $foreign = Lead::factory()->ownedBy(User::factory()->create()->id)->create();

    expect(Lead::visibleTo($manager)->pluck('id'))->toContain($foreign->id)
        ->and($manager->can('view', $foreign))->toBeTrue();
});
