<?php

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('redirects guests to login', function () {
    $this->get('/menu-controller')->assertRedirect('/login');
});

it('blocks a hidden menu route via middleware even though it is not in the sidebar', function () {
    // Menu Controller is admin-only, so it is hidden from a sales user's
    // sidebar. Hitting the route directly must still be blocked server-side.
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get('/menu-controller')->assertForbidden();
});

it('allows a role to reach a route it is granted', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->get('/leads')->assertOk();
});

it('lets an admin reach every route', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->get('/menu-controller')->assertOk();
    $this->actingAs($admin)->get('/invoices')->assertOk();
});

it('treats a per-user grant as cosmetic only — it shows the menu but does not open the route', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $menuController = MenuItem::where('key', 'menu-controller')->firstOrFail();

    // Cosmetically reveal the item for this user.
    $sales->menuOverrides()->attach($menuController->id, ['access' => 'granted']);

    // The sidebar now shows it...
    $this->actingAs($sales)->get('/dashboard')->assertOk()->assertSee('Menu Controller');

    // ...but the route is still forbidden, because access is role-based.
    $this->actingAs($sales)->get('/menu-controller')->assertForbidden();
});

it('treats a per-user revoke as cosmetic only — it hides the menu but keeps access', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $leads = MenuItem::where('key', 'lead-generation')->firstOrFail();

    $sales->menuOverrides()->attach($leads->id, ['access' => 'revoked']);

    // Hidden from the sidebar...
    $this->actingAs($sales)->get('/dashboard')->assertOk()->assertDontSee('Lead Generation');

    // ...yet the route remains reachable, since the role still grants access.
    $this->actingAs($sales)->get('/leads')->assertOk();
});
