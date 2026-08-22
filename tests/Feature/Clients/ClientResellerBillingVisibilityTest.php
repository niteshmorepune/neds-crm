<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lists a reseller-billed invoice on the referred client\'s own Invoices tab, tagged with who it was billed to', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);
    $client = Customer::factory()->create(['company_name' => 'TMR']);
    $project = Project::factory()->create(['customer_id' => $client->id]);
    Invoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'invoice_number' => 'NEDS/2026-27/0265']);

    $this->actingAs($this->admin)->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('NEDS/2026-27/0265')
        ->assertSee('Billed via Brand Whiz')
        ->assertSee('Invoices (1)');
});

it('does not count a reseller-billed invoice as this client\'s own Total Revenue', function () {
    // The invoice's own amount legitimately appears in its Invoices-tab row
    // (that's the point of this fix) — so this asserts the underlying
    // relation the Total Revenue tile sums from stays untouched, rather
    // than a fragile "amount doesn't appear anywhere on the page" check.
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);
    $client = Customer::factory()->create(['company_name' => 'TMR']);
    $project = Project::factory()->create(['customer_id' => $client->id]);
    Invoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'total' => 590000]);

    $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk();

    $client->load('invoices');
    expect((int) $client->invoices->sum('total'))->toBe(0);
});

it('lists a reseller-billed recurring template on the referred client\'s Services tab, tagged with who it was billed to', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz', 'state_code' => '27']);
    $client = Customer::factory()->create(['company_name' => 'TMR', 'state_code' => '27']);
    $project = Project::factory()->create(['customer_id' => $client->id]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'is_active' => true]);
    $template->items()->create(['description' => 'Social Media Management for TMR', 'sac_code' => '998314', 'quantity' => 1, 'rate' => 500000, 'gst_rate' => 18]);

    $this->actingAs($this->admin)->get(route('clients.show', $client))
        ->assertOk()
        ->assertDontSee('No recurring services set up for this client.')
        ->assertSee('Billed via Brand Whiz');
});

it('does not count a reseller-billed recurring template in this client\'s own MRR tile', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz', 'state_code' => '27']);
    $client = Customer::factory()->create(['company_name' => 'TMR', 'state_code' => '27']);
    $project = Project::factory()->create(['customer_id' => $client->id]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'is_active' => true]);
    $template->items()->create(['description' => 'Social Media Management for TMR', 'sac_code' => '998314', 'quantity' => 1, 'rate' => 500000, 'gst_rate' => 18]);

    $client->load('recurringInvoices.items');
    expect($client->monthlyRecurringValue())->toBe(0);
});

it('still shows a reseller-billed recurring template to a role without invoice access, but hides the reseller-billed invoice', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz', 'state_code' => '27']);
    $client = Customer::factory()->create(['company_name' => 'TMR', 'state_code' => '27']);
    $project = Project::factory()->create(['customer_id' => $client->id]);
    $template = RecurringInvoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'is_active' => true]);
    $template->items()->create(['description' => 'Social Media Management for TMR', 'sac_code' => '998314', 'quantity' => 1, 'rate' => 500000, 'gst_rate' => 18]);
    Invoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'invoice_number' => 'NEDS/2026-27/0265']);

    $html = $this->actingAs($support)->get(route('clients.show', $client))->assertOk()->getContent();

    expect($html)->toContain('Billed via Brand Whiz') // the recurring template row still renders
        ->not->toContain('NEDS/2026-27/0265'); // but the invoice itself stays gated
});

it('leaves a normal (non-reseller) client\'s Invoices and Services tabs unaffected', function () {
    $client = Customer::factory()->create();
    Invoice::factory()->for($client)->create(['invoice_number' => 'NEDS/2026-27/0001']);

    $html = $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->getContent();

    expect($html)->toContain('NEDS/2026-27/0001')
        ->not->toContain('Billed via');
});
