<?php

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
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

it('shows a client feedback summary on the tickets tab when a rating exists', function () {
    $client = Customer::factory()->create();
    $ticket = Ticket::factory()->create(['customer_id' => $client->id]);
    $ticket->satisfactionRating()->create(['rating' => 5, 'comment' => 'Great support']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('avg 5')
        ->assertSee('Great support');
});

it('offers edit and delete on a Won deal from the client deals tab', function () {
    $client = Customer::factory()->create();
    $deal = Deal::factory()->stage(DealStage::Won)->create(['customer_id' => $client->id, 'title' => 'Won Deal']);

    $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->getContent();

    expect($html)->toContain('Won Deal')
        ->toContain(route('deals.destroy', $deal));
});

it('deletes a Won deal from the client deals tab', function () {
    $client = Customer::factory()->create();
    $deal = Deal::factory()->stage(DealStage::Won)->create(['customer_id' => $client->id]);

    $this->actingAs($this->admin)->delete(route('deals.destroy', $deal))->assertRedirect();

    expect(Deal::find($deal->id))->toBeNull();
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

it('orders recurring services chronologically within each service, not by creation order', function () {
    $service = Service::factory()->create(['name' => 'Social Media']);
    $client = Customer::factory()->create(['company_name' => 'Chrono Co']);

    // Created out of chronological order (May, then July, then June) — the
    // real bug this reproduces: a same-service block reading
    // "19 May / 19 Jul / 19 Jun" instead of ascending.
    RecurringInvoice::factory()->create([
        'customer_id' => $client->id, 'service_id' => $service->id,
        'frequency' => RecurringFrequency::Monthly, 'start_date' => '2026-05-19', 'end_date' => '2026-06-18',
    ]);
    RecurringInvoice::factory()->create([
        'customer_id' => $client->id, 'service_id' => $service->id,
        'frequency' => RecurringFrequency::Monthly, 'start_date' => '2026-07-19', 'end_date' => '2026-08-18',
    ]);
    RecurringInvoice::factory()->create([
        'customer_id' => $client->id, 'service_id' => $service->id,
        'frequency' => RecurringFrequency::Monthly, 'start_date' => '2026-06-19', 'end_date' => '2026-07-18',
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSeeInOrder(['19 May 2026', '19 Jun 2026', '19 Jul 2026']);
});

it('excludes Ended templates from the "On hold" summary count', function () {
    $service = Service::factory()->create(['name' => 'Social Media']);
    $client = Customer::factory()->create(['company_name' => 'Mixed Status Co']);

    // 2 naturally-ended (should NOT count as "On hold").
    RecurringInvoice::factory()->count(2)->create([
        'customer_id' => $client->id, 'service_id' => $service->id,
        'is_active' => false, 'start_date' => now()->subMonths(2), 'end_date' => now()->subMonth(),
    ]);
    // 1 genuinely paused (SHOULD count as "On hold").
    RecurringInvoice::factory()->create([
        'customer_id' => $client->id, 'service_id' => $service->id,
        'is_active' => false, 'end_date' => null,
    ]);
    // 1 active.
    RecurringInvoice::factory()->create([
        'customer_id' => $client->id, 'service_id' => $service->id, 'is_active' => true,
    ]);

    $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->getContent();
    preg_match('/On hold<\/dt>\s*<dd[^>]*>\s*(\d+)/', $html, $matches);

    expect($matches[1] ?? null)->toBe('1');
});

it('labels a one-cycle recurring invoice that actually billed and was paid "Payment Received"', function () {
    // Confirms the client page's revealPaymentStatus=true path — "Ended" is
    // never shown to an Admin/Manager viewer once an invoice exists; that's
    // reserved for a viewer without invoice access (see the next test) or a
    // period that ended without ever billing (see the "Not Billed" test).
    $service = Service::factory()->create(['name' => 'GMB']);
    $client = Customer::factory()->create(['company_name' => 'Finished Cycle Co']);

    $recurring = RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => false,
        'start_date' => now()->subMonths(2),
        'end_date' => now()->subMonth(),
        'next_run_on' => now()->addMonth(),
    ]);
    Invoice::factory()->status(InvoiceStatus::Paid)->create([
        'recurring_invoice_id' => $recurring->id, 'customer_id' => $client->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Payment Received')
        ->assertDontSee('On Hold');
});

it('labels a one-cycle recurring invoice that never billed "Not Billed", not "Ended"', function () {
    // 2026-07-24 fix: this used to say "Ended", which wrongly implied a
    // completed billing cycle for a template that never actually billed
    // anything — real production data showed staff using paused,
    // never-billed templates to log historical service periods.
    $service = Service::factory()->create(['name' => 'GMB']);
    $client = Customer::factory()->create(['company_name' => 'Never Billed Co']);

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
        ->assertSee('Not Billed')
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

it('hides an orphaned recurring invoice (billed then invoice deleted, never reactivated) from the services tab entirely', function () {
    $service = Service::factory()->create(['name' => 'Social Media']);
    $client = Customer::factory()->create(['company_name' => 'Orphan Co']);

    // The one-cycle, invoice-deleted "ghost" pattern: paused, no surviving
    // invoice, but an invoice WAS generated at some point (now soft-deleted).
    $orphaned = RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => false,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
    Invoice::factory()->create(['recurring_invoice_id' => $orphaned->id, 'customer_id' => $client->id])->delete();

    // A genuinely paused-but-still-billed template for the same service —
    // must stay visible; only the orphaned one should disappear.
    $onHold = RecurringInvoice::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'is_active' => false,
        'start_date' => now()->subMonth(),
        'end_date' => now()->addMonth(),
    ]);
    Invoice::factory()->create(['recurring_invoice_id' => $onHold->id, 'customer_id' => $client->id]);

    $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->getContent();
    preg_match('/On hold<\/dt>\s*<dd[^>]*>\s*(\d+)/', $html, $matches);

    // Only the surviving-invoice template counts — the orphan is excluded
    // from both the row list and the summary strip count.
    expect($matches[1] ?? null)->toBe('1');
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
