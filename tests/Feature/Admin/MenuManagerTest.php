<?php

use App\Enums\UserRole;
use App\Livewire\MenuManager;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\MenuResolver;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('is reachable only by an admin', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())
        ->get(route('menu-controller'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('menu-controller'))->assertOk()->assertSee('Menu Controller');
});

it('toggling a role default grants and revokes actual route access', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $item = MenuItem::where('key', 'invoices')->firstOrFail(); // sales has no invoices access by default

    expect(app(MenuResolver::class)->canAccess($sales, 'invoices'))->toBeFalse();

    Livewire::actingAs($this->admin)->test(MenuManager::class)
        ->call('toggleRole', $item->id, UserRole::Sales->value);

    expect(app(MenuResolver::class)->canAccess($sales->fresh(), 'invoices'))->toBeTrue();

    Livewire::actingAs($this->admin)->test(MenuManager::class)
        ->call('toggleRole', $item->id, UserRole::Sales->value);

    expect(app(MenuResolver::class)->canAccess($sales->fresh(), 'invoices'))->toBeFalse();
});

it('a per-user override hides a sidebar item without removing access', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $leads = MenuItem::where('key', 'lead-generation')->firstOrFail();

    Livewire::actingAs($this->admin)->test(MenuManager::class)
        ->set('selectedUserId', $sales->id)
        ->call('setOverride', $leads->id, 'revoked');

    $visibleKeys = app(MenuResolver::class)->visibleItems($sales->fresh())->pluck('key');

    // Hidden from the sidebar…
    expect($visibleKeys)->not->toContain('lead-generation')
        // …but route access is unchanged.
        ->and(app(MenuResolver::class)->canAccess($sales->fresh(), 'lead-generation'))->toBeTrue();
});

it('forbids a non-admin from even mounting the component', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    Livewire::actingAs($manager)->test(MenuManager::class)->assertForbidden();
});
