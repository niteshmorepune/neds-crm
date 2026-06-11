<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows the audit log to an admin and records create/update events', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $this->actingAs($admin);

    $customer = Customer::factory()->create(['company_name' => 'Audited Co']); // logs "created"
    $customer->update(['phone' => '9999999999']); // logs "updated"

    $this->get(route('audit-log'))->assertOk()
        ->assertSee('Customer')
        ->assertSee('Created')
        ->assertSee('Updated');
});

it('forbids non-admins from the audit log', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->get(route('audit-log'))->assertForbidden();
});

it('filters the audit log by event', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $this->actingAs($admin);

    $c = Customer::factory()->create(['company_name' => 'Filterable Co']);
    $c->update(['phone' => '8888888888']);

    // Only deleted events — our created/updated rows must not show.
    $this->get(route('audit-log', ['event' => 'deleted']))->assertOk()
        ->assertSee('No activity recorded');
});
