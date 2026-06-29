<?php

use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('admin can list partners', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->get(route('partners.index'))
        ->assertOk();
});

it('manager can list partners', function () {
    actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->get(route('partners.index'))
        ->assertOk();
});

it('sales cannot access partners', function () {
    actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->get(route('partners.index'))
        ->assertForbidden();
});

it('admin can create a partner', function () {
    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->post(route('partners.store'), [
            'name' => 'Test Agency',
            'email' => 'agency@example.com',
            'phone' => '9876543210',
            'notes' => 'Our primary content partner.',
        ])
        ->assertRedirect(route('partners.index'));

    expect(Partner::where('name', 'Test Agency')->exists())->toBeTrue();
});

it('manager can update a partner', function () {
    $partner = Partner::factory()->create();

    actingAs(User::factory()->create(['role' => UserRole::Manager]))
        ->put(route('partners.update', $partner), [
            'name' => 'Updated Agency',
            'email' => null,
            'phone' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('partners.index'));

    expect($partner->fresh()->name)->toBe('Updated Agency');
});

it('admin can delete a partner', function () {
    $partner = Partner::factory()->create();

    actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->delete(route('partners.destroy', $partner))
        ->assertRedirect(route('partners.index'));

    expect(Partner::find($partner->id))->toBeNull();
});

it('support cannot create a partner', function () {
    actingAs(User::factory()->create(['role' => UserRole::Support]))
        ->post(route('partners.store'), ['name' => 'Sneaky Agency'])
        ->assertForbidden();
});
