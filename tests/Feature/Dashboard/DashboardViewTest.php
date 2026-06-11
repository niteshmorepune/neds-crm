<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows the company dashboard to an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee('Total Clients')
        ->assertSee('Services Overview')
        ->assertSee('Task Summary');
});

it('shows the sales panel to a sales rep', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get(route('dashboard'))->assertOk()
        ->assertSee('Open pipeline by stage')
        ->assertDontSee('Services Overview');
});

it('shows the accounts panel to an accounts user', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->get(route('dashboard'))->assertOk()
        ->assertSee('Outstanding receivables');
});

it('shows the support panel to a support user', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('dashboard'))->assertOk()
        ->assertSee('Open tickets by priority');
});
