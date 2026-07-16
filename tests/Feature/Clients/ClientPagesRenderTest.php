<?php

use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Service;
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
    $support = User::factory()->role(UserRole::Support)->create();
    $client = Customer::factory()->create();

    $this->actingAs($support)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('have access to invoices');
});

it('shows invoice data on the client tab to sales, who have invoice access by default', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $client = Customer::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertDontSee('have access to invoices');
});

it('renders the services tab with recurring services and projects', function () {
    $service = Service::factory()->create(['name' => 'SEO']);
    $client = Customer::factory()->create(['company_name' => 'Services Co']);

    RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => true,
        'frequency' => RecurringFrequency::Monthly,
        'start_date' => now()->subMonths(3),
        'next_run_on' => now()->addDays(10),
    ]);

    Project::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'name' => 'SEO Launch',
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Services Co')
        ->assertSee('Recurring Services')
        ->assertSee('SEO')
        ->assertSee('Active')
        ->assertSee('Monthly')
        ->assertSee('Projects')
        ->assertSee('SEO Launch');
});

it('labels a naturally-finished one-cycle recurring invoice "Ended", not "On Hold"', function () {
    $service = Service::factory()->create(['name' => 'GMB']);
    $client = Customer::factory()->create(['company_name' => 'Finished Cycle Co']);

    RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => false,
        'start_date' => now()->subMonths(2),
        'end_date' => now()->subMonth(),
        'next_run_on' => now()->addMonth(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Ended')
        ->assertDontSee('On Hold');
});

it('still labels a manually-paused recurring invoice "On Hold"', function () {
    $service = Service::factory()->create(['name' => 'SEO']);
    $client = Customer::factory()->create(['company_name' => 'Paused Co']);

    RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => false,
        'end_date' => null,
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('On Hold')
        ->assertDontSee('Ended');
});

it('hides the +GST hint on the services tab for a GST-exempt recurring invoice', function () {
    $service = Service::factory()->create(['name' => 'AMC Service']);
    $client = Customer::factory()->create(['company_name' => 'Exempt Co']);

    $exempt = RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => true,
        'is_gst_exempt' => true,
    ]);
    $exempt->items()->create([
        'description' => 'AMC retainer', 'sac_code' => '998313',
        'quantity' => 1, 'rate' => 300000, 'gst_rate' => 18,
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('AMC Service')
        ->assertDontSee('+GST');
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
