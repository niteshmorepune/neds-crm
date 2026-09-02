<?php

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('shows status summary tiles with counts unaffected by the list filters', function () {
    Customer::factory()->count(2)->create(['status' => CustomerStatus::Active]);
    Customer::factory()->count(3)->create(['status' => CustomerStatus::Prospect]);
    Customer::factory()->create(['status' => CustomerStatus::Inactive]);

    // Filtering the list to just one status must not change the tiles.
    $response = $this->actingAs($this->admin)->get(route('clients.index', ['status' => CustomerStatus::Inactive->value]));

    $response->assertOk()
        ->assertViewHas('statusCounts', [
            'total' => 6,
            'active' => 2,
            'prospect' => 3,
            'inactive' => 1,
        ]);
});

it('makes each status tile a link that filters the list to that status, giving Sales a dedicated Prospect view', function () {
    Customer::factory()->create(['company_name' => 'Active Client Co', 'status' => CustomerStatus::Active]);
    Customer::factory()->create(['company_name' => 'On The Way Co', 'status' => CustomerStatus::Prospect]);

    $html = $this->actingAs($this->admin)->get(route('clients.index'))->getContent();

    expect($html)->toContain('href="'.route('clients.index', ['status' => 'all']).'"')
        ->and($html)->toContain('href="'.route('clients.index', ['status' => CustomerStatus::Prospect->value]).'"');

    $this->actingAs($this->admin)
        ->get(route('clients.index', ['status' => CustomerStatus::Prospect->value]))
        ->assertOk()->assertSee('On The Way Co')->assertDontSee('Active Client Co');
});

it('highlights the currently active status tile', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('clients.index', ['status' => CustomerStatus::Prospect->value]))
        ->getContent();

    expect(substr_count($html, 'ring-2 ring-indigo-400'))->toBe(1);
});
