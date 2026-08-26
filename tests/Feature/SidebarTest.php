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

it('marks the active sidebar link and force-opens its group, even when a further-down group would otherwise render first', function () {
    // Real bug (2026-08-26): with ~30 items across 6 groups, the active
    // item could be scrolled well off-screen with no visible cue where
    // "you are here" is — the fix is a data-active-menu-item marker (a
    // small script scrolls it into view) plus forcing that item's own
    // group open regardless of any previously-collapsed state.
    $admin = User::factory()->role(UserRole::Admin)->create();

    $html = $this->actingAs($admin)->get(route('projects.index'))->assertOk()->getContent();

    expect($html)->toContain('data-active-menu-item')
        ->toContain('open: true');
});

it('includes the sidebar active-item scroll script', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk()
        ->assertSee('data-active-menu-item', false)
        ->assertSee('scrollIntoView', false);
});
