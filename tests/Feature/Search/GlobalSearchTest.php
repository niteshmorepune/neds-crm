<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('finds records across sections for an admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Customer::factory()->create(['company_name' => 'Zephyr Tech Pvt Ltd']);
    Lead::factory()->create(['name' => 'Zephyr lead', 'company' => 'Zephyr']);

    $this->actingAs($admin)->get(route('search', ['q' => 'Zephyr']))->assertOk()
        ->assertSee('Zephyr Tech Pvt Ltd')
        ->assertSee('Zephyr lead')
        ->assertSee('Clients')
        ->assertSee('Leads');
});

it('ignores a query shorter than two characters', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Customer::factory()->create(['company_name' => 'Z Corp']);

    $this->actingAs($admin)->get(route('search', ['q' => 'Z']))->assertOk()
        ->assertSee('No results');
});

it('does not search sections the user cannot access', function () {
    // Support has no Invoices access, so a matching invoice number must not
    // show — but Support does have Tickets access, so a matching ticket should.
    $support = User::factory()->role(UserRole::Support)->create();
    Invoice::factory()->create(['invoice_number' => 'INV-QUASAR-1']);
    Ticket::factory()->create(['subject' => 'Quasar login issue']);

    $this->actingAs($support)->get(route('search', ['q' => 'Quasar']))->assertOk()
        ->assertSee('Quasar login issue')
        ->assertDontSee('INV-QUASAR-1');
});

it('shows all leads to sales users (no owner-based filter)', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($other->id)->create(['name' => 'Nebula visible']); // owned by someone else

    // Sales now sees all leads — no owner restriction.
    $this->actingAs($sales)->get(route('search', ['q' => 'Nebula']))->assertOk()
        ->assertSee('Nebula visible');
});
