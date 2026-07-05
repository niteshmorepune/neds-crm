<?php

use App\Enums\UserRole;
use App\Models\Festival;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets a manager manage festivals but forbids a sales user', function () {
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('festivals.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('festivals.index'))->assertForbidden();
});

it('adds a festival', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('festivals.store'), [
        'name' => 'Diwali',
        'date' => '2026-11-08',
        'notes' => 'Confirm with regional calendar',
    ])->assertRedirect();

    $festival = Festival::firstWhere('name', 'Diwali');
    expect($festival)->not->toBeNull()
        ->and($festival->date->toDateString())->toBe('2026-11-08')
        ->and($festival->is_active)->toBeFalse(); // checkbox not sent on the add form
});

it('updates and deactivates a festival', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $festival = Festival::factory()->create(['name' => 'Holi', 'is_active' => true]);

    $this->actingAs($admin)->put(route('festivals.update', $festival), [
        'name' => 'Holi (Rang Panchami)',
        'date' => $festival->date->toDateString(),
        // is_active omitted => deactivate
    ])->assertRedirect();

    $festival->refresh();
    expect($festival->name)->toBe('Holi (Rang Panchami)')
        ->and($festival->is_active)->toBeFalse();
});

it('deletes a festival', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $festival = Festival::factory()->create();

    $this->actingAs($admin)->delete(route('festivals.destroy', $festival))->assertRedirect();

    expect(Festival::find($festival->id))->toBeNull();
});
