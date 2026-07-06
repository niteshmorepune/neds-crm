<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets an admin view the client radar page', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Customer::factory()->create(); // no touches at all -> flagged

    $this->actingAs($admin)->get(route('client-radar.index'))
        ->assertOk()
        ->assertSee('Client Radar');
});

it('lets a manager view the client radar page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('client-radar.index'))->assertOk();
});

it('forbids sales from viewing the client radar page', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('client-radar.index'))->assertForbidden();
});

it('shows the dashboard banner to an admin when a client is flagged', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Customer::factory()->create(); // no touches -> flagged

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('client')
        ->assertSee('needs attention');
});

it('does not show the dashboard banner when no client is flagged', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('needs attention');
});

it('does not show the dashboard banner to non-admin/manager roles', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Customer::factory()->ownedBy($sales->id)->create();

    $this->actingAs($sales)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('needs attention');
});
