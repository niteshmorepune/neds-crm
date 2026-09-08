<?php

use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows a Sales rep their own stalling leads but not the team-wide section', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Budget, 'name' => 'Mine']);
    Lead::factory()->create(['owner_id' => $other->id, 'stall_reason' => StallReason::Trust, 'name' => 'Not Mine']);

    $this->actingAs($sales)->get(route('stalling.index'))
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Not Mine')
        ->assertDontSee('By reason, whole team');
});

it('shows a Manager the team-wide section and the reason breakdown', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Budget, 'name' => 'Someone Else\'s Lead']);

    $this->actingAs($manager)->get(route('stalling.index'))
        ->assertOk()
        ->assertSee('By reason, whole team')
        ->assertSee('Someone Else')
        ->assertSee('Budget / financial constraint');
});

it('forbids a role with no access to the sales pipeline', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('stalling.index'))->assertForbidden();
});

it('shows an empty state when nothing is tagged', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('stalling.index'))
        ->assertOk()
        ->assertSee('Nothing tagged as stalling right now.');
});
