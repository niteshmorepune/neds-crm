<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('finds a client by a phone number substring', function () {
    // Real gap, reported 2026-09-23: clients were not searchable by mobile
    // number at all on the Clients list — the search filter only ever
    // checked company_name/email/gstin.
    $match = Customer::factory()->create(['company_name' => 'Priya Enterprises', 'phone' => '+91 98765 43210']);
    $other = Customer::factory()->create(['company_name' => 'Someone Else Co', 'phone' => '+91 90000 00000']);

    $response = $this->actingAs($this->admin)->get(route('clients.index', ['search' => '9876543210']));

    $ids = $response->viewData('customers')->pluck('id');
    expect($ids)->toContain($match->id)->not->toContain($other->id);
});

it('finds a client by an alternate phone number substring', function () {
    $match = Customer::factory()->create(['phone' => '+91 98765 43210', 'alternate_phone' => '+91 88888 77777']);

    $response = $this->actingAs($this->admin)->get(route('clients.index', ['search' => '8888877777']));

    expect($response->viewData('customers')->pluck('id'))->toContain($match->id);
});
