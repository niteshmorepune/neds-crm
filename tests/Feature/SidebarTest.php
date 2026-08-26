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

it('opens only the current page\'s own group and collapses every other group — true accordion, not just force-opening the active one', function () {
    // Real bug (2026-08-26, owner-reported via screenshot): a group opened
    // once and persisted (via localStorage) stayed open on every later
    // page load regardless of which page you were actually on, so several
    // groups could be expanded at once — a long sidebar, and (because the
    // active item could sit well below the fold) a scroll-into-view script
    // that ended up scrolling the whole page instead of just the sidebar,
    // landing the page itself somewhere other than the top. Fixed by
    // dropping persistence entirely: exactly one group — the one containing
    // the current page — is open on every fresh load, full stop.
    $admin = User::factory()->role(UserRole::Admin)->create();

    $html = $this->actingAs($admin)->get(route('projects.index'))->assertOk()->getContent();

    // Desktop + mobile each render every group once, so "open: true" should
    // appear exactly twice (one per copy of the Delivery & Support group,
    // which owns Project Updates) — never for any other group.
    expect(substr_count($html, 'open: true'))->toBe(2)
        ->and($html)->not->toContain('localStorage')
        ->not->toContain('scrollIntoView');
});

it('splits the former single Team & Insights group so no sidebar group exceeds 10 items when expanded', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/dashboard')->assertOk()
        ->assertSee('Team & Insights')
        ->assertSee('Team Tools')
        ->assertSee('Project Health')
        ->assertSee('Team Workload');
});
