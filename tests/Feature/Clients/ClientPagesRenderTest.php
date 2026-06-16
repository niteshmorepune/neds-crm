<?php

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

// Renders the actual Blade views (incl. the data-driven sidebar) end-to-end —
// the gap that let a "Route [customer] not defined" error reach the browser.
beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('renders the clients index', function () {
    Customer::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Clients');
});

it('renders the create form', function () {
    $this->actingAs($this->admin)
        ->get(route('clients.create'))
        ->assertOk()
        ->assertSee('Company name');
});

it('renders the client detail page with contacts and tabs', function () {
    $client = Customer::factory()->create(['company_name' => 'Render Co']);
    Contact::factory()->primary()->create(['customer_id' => $client->id, 'name' => 'Primary Person']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Render Co')
        ->assertSee('Primary Person')
        ->assertSee('Contacts')
        ->assertSee('Notes');
});

it('shows real deals and tickets data on the client tabs', function () {
    $client = Customer::factory()->create(['company_name' => 'Render Co']);
    $deal = Deal::factory()->create(['customer_id' => $client->id, 'title' => 'Website Revamp']);
    $ticket = Ticket::factory()->create(['customer_id' => $client->id, 'subject' => 'Login issue']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Website Revamp')
        ->assertSee('Login issue');
});

it('hides invoice data on the client tab from a role without invoice access', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $client = Customer::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('have access to invoices');
});

it('renders the edit form', function () {
    $client = Customer::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('clients.edit', $client))
        ->assertOk()
        ->assertSee('Save Changes');
});

it('renders the CSV import page', function () {
    $this->actingAs($this->admin)
        ->get(route('clients.import'))
        ->assertOk()
        ->assertSee('Import Clients');
});
