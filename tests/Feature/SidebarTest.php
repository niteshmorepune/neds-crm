<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a user log in and reach the dashboard', function () {
    $user = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('renders an admin sidebar containing every item including Menu Controller', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $response = $this->actingAs($admin)->get('/dashboard')->assertOk();

    $response->assertSee('Menu Controller');
    $response->assertSee('Lead Generation');
    $response->assertSee('Invoices');
    // Label override from CLAUDE.md: "Customer" entity shown as "Clients".
    $response->assertSee('Clients');
});

it('hides items a sales user has no access to', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $response = $this->actingAs($sales)->get('/dashboard')->assertOk();

    // Sales can see their own items...
    $response->assertSee('Lead Generation');
    $response->assertSee('Clients');

    // ...but not admin/manager-only items.
    $response->assertDontSee('Menu Controller');
    $response->assertDontSee('Partners');
});
