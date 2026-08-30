<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->accounts = User::factory()->role(UserRole::Accounts)->create();
    $this->customer = Customer::factory()->create(['company_name' => 'Acme Corp']);
});

it('renders the create form for accounts', function () {
    $this->actingAs($this->accounts)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertSee('Log Invoice');
});

it('renders the Add GST line items toggle and defaults new rows to SAC 998314 / 18%', function () {
    $this->actingAs($this->accounts)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertSee('Add GST line items now')
        ->assertSee('998314');
});

it('does not hardcode the flat-mode Amount field as required, since that silently blocks submission when it is hidden behind the GST line items toggle', function () {
    // Regression: the field previously used :required="old('mode') !== 'items'",
    // a Blade conditional baked in at page load -- true on a fresh page load
    // regardless of the live Alpine itemsMode state. A `required` input
    // hidden via x-show="!itemsMode" fails native browser validation with
    // NO visible error anywhere, silently blocking the Log Invoice button
    // the moment someone checks "Add GST line items now". Fixed to track
    // the same live x-show boolean via x-bind:required="!itemsMode".
    $html = $this->actingAs($this->accounts)->get(route('invoices.create'))->getContent();
    preg_match('/<input[^>]*id="amount"[^>]*>/', $html, $matches);

    expect($matches)->not->toBeEmpty()
        ->and($matches[0])->toContain('x-bind:required="!itemsMode"');
});

it('preselects the client and project when deep-linked from the client/project page', function () {
    $project = Project::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->accounts)
        ->get(route('invoices.create', ['customer_id' => $this->customer->id, 'project_id' => $project->id]))
        ->assertOk()
        ->assertSee('value="'.$this->customer->id.'" selected', false)
        ->assertSee('value="'.$project->id.'" selected', false);
});

it('ignores an invalid or unknown customer_id/project_id query param instead of erroring', function () {
    $this->actingAs($this->accounts)
        ->get(route('invoices.create', ['customer_id' => 'not-a-number', 'project_id' => 999999]))
        ->assertOk();
});

it('logs an invoice with required fields', function () {
    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-0001',
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-07-01',
            'amount' => '50000',
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-0001')->firstOrFail();

    expect($invoice->total)->toBe(5000000) // 50000 rupees in paise
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->customer_id)->toBe($this->customer->id)
        ->and($invoice->financial_year)->toBe('2026-27');
});

it('logs an invoice linked to a deal and project', function () {
    $deal = Deal::factory()->create(['customer_id' => $this->customer->id]);
    $project = Project::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-0002',
            'customer_id' => $this->customer->id,
            'deal_id' => $deal->id,
            'project_id' => $project->id,
            'issue_date' => '2026-07-01',
            'amount' => '10000',
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-0002')->firstOrFail();

    expect($invoice->deal_id)->toBe($deal->id)
        ->and($invoice->project_id)->toBe($project->id);
});

it('logs a reseller-referred client\'s invoice to the reseller\'s own customer record', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);
    $reseller = Partner::factory()->create(['name' => 'Brand-Whiz', 'billing_customer_id' => $billTo->id]);
    $client = Customer::factory()->create(['company_name' => 'ESS', 'referring_partner_id' => $reseller->id]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-0099',
            'customer_id' => $client->id,
            'issue_date' => '2026-07-01',
            'amount' => '10000',
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-0099')->firstOrFail();

    expect($invoice->customer_id)->toBe($billTo->id);
});

it('logs an invoice with GST line items in one submission, no separate Add GST Line Items step needed', function () {
    $customer = Customer::factory()->create(['state_code' => '27']);

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-ITEMS-1',
            'customer_id' => $customer->id,
            'issue_date' => '2026-07-01',
            'mode' => 'items',
            'items' => [
                ['description' => 'Social Media Management', 'sac_code' => '998314', 'quantity' => '1', 'rate' => '5000', 'gst_rate' => '18'],
            ],
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-ITEMS-1')->firstOrFail();

    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->subtotal)->toBe(500000)
        ->and($invoice->cgst_total)->toBe(45000)
        ->and($invoice->sgst_total)->toBe(45000)
        ->and($invoice->total)->toBe(590000)
        ->and($invoice->place_of_supply_state_code)->toBe('27')
        ->and($invoice->is_intra_state)->toBeTrue();
});

it('correctly charges IGST instead of CGST+SGST for an inter-state client logged with GST line items', function () {
    $customer = Customer::factory()->create(['state_code' => '09']); // Uttar Pradesh

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-ITEMS-2',
            'customer_id' => $customer->id,
            'issue_date' => '2026-07-01',
            'mode' => 'items',
            'items' => [
                ['description' => 'Consulting', 'sac_code' => '998314', 'quantity' => '1', 'rate' => '5000', 'gst_rate' => '18'],
            ],
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-ITEMS-2')->firstOrFail();

    expect($invoice->is_intra_state)->toBeFalse()
        ->and($invoice->igst_total)->toBe(90000)
        ->and($invoice->cgst_total)->toBe(0)
        ->and($invoice->sgst_total)->toBe(0);
});

it('logs a reseller-referred client\'s itemized invoice to the reseller\'s own customer record too', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz', 'state_code' => '27']);
    $reseller = Partner::factory()->create(['name' => 'Brand-Whiz', 'billing_customer_id' => $billTo->id]);
    $client = Customer::factory()->create(['company_name' => 'TMR', 'referring_partner_id' => $reseller->id, 'state_code' => '01']);

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-ITEMS-3',
            'customer_id' => $client->id,
            'issue_date' => '2026-07-01',
            'mode' => 'items',
            'items' => [
                ['description' => 'Social Media Management', 'sac_code' => '998314', 'quantity' => '1', 'rate' => '5000', 'gst_rate' => '18'],
            ],
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-ITEMS-3')->firstOrFail();

    // Billed to (and taxed by the state of) the reseller, not the referred client.
    expect($invoice->customer_id)->toBe($billTo->id)
        ->and($invoice->place_of_supply_state_code)->toBe('27')
        ->and($invoice->is_intra_state)->toBeTrue();
});

it('sets place of supply from the customer even for a flat (non-itemized) logged invoice', function () {
    $customer = Customer::factory()->create(['state_code' => '19']); // West Bengal

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-FLAT-POS',
            'customer_id' => $customer->id,
            'issue_date' => '2026-07-01',
            'amount' => '5000',
        ])
        ->assertRedirect();

    $invoice = Invoice::where('invoice_number', 'HT-2026-FLAT-POS')->firstOrFail();

    expect($invoice->place_of_supply_state_code)->toBe('19');
});

it('rejects logging an invoice with mode=items but no items', function () {
    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-2026-BAD-ITEMS',
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-07-01',
            'mode' => 'items',
        ])
        ->assertSessionHasErrors('items');

    expect(Invoice::where('invoice_number', 'HT-2026-BAD-ITEMS')->exists())->toBeFalse();
});

it('rejects a duplicate invoice number', function () {
    Invoice::factory()->create(['invoice_number' => 'HT-DUPE-001', 'customer_id' => $this->customer->id]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-DUPE-001',
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-07-01',
            'amount' => '5000',
        ])
        ->assertSessionHasErrors('invoice_number');
});

it('allows reusing an invoice number that only a soft-deleted invoice holds', function () {
    Invoice::factory()->create(['invoice_number' => 'HT-DUPE-002', 'customer_id' => $this->customer->id])->delete();

    $this->actingAs($this->accounts)
        ->post(route('invoices.store'), [
            'invoice_number' => 'HT-DUPE-002',
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-07-01',
            'amount' => '5000',
        ])
        ->assertSessionDoesntHaveErrors('invoice_number');

    expect(Invoice::where('invoice_number', 'HT-DUPE-002')->count())->toBe(1);
});

it('edits a logged invoice', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'HT-EDIT-001',
        'customer_id' => $this->customer->id,
        'issue_date' => '2026-07-01',
        'total' => 5000000,
        'subtotal' => 5000000,
        'taxable_total' => 5000000,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($this->accounts)
        ->put(route('invoices.update', $invoice), [
            'invoice_number' => 'HT-EDIT-001-REV',
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-07-05',
            'amount' => '60000',
        ])
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()->invoice_number)->toBe('HT-EDIT-001-REV')
        ->and($invoice->fresh()->total)->toBe(6000000);
});

it('allows editing an invoice to a number that only a soft-deleted invoice holds', function () {
    Invoice::factory()->create(['invoice_number' => 'HT-DUPE-003', 'customer_id' => $this->customer->id])->delete();

    $invoice = Invoice::factory()->create([
        'invoice_number' => 'NEDS/2026-27/1115',
        'customer_id' => $this->customer->id,
        'issue_date' => '2026-07-01',
        'total' => 5000000,
        'subtotal' => 5000000,
        'taxable_total' => 5000000,
        'status' => InvoiceStatus::Sent,
    ]);

    $this->actingAs($this->accounts)
        ->put(route('invoices.update', $invoice), [
            'invoice_number' => 'HT-DUPE-003',
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-07-05',
            'amount' => '50000',
        ])
        ->assertSessionDoesntHaveErrors('invoice_number')
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->fresh()->invoice_number)->toBe('HT-DUPE-003');
});

it('renders the import form', function () {
    $this->actingAs($this->accounts)
        ->get(route('invoices.import'))
        ->assertOk()
        ->assertSee('Import Invoices from CSV');
});

it('imports valid rows from CSV and reports skipped ones', function () {
    Customer::factory()->create(['company_name' => 'Beta Solutions']);

    $csv = implode("\n", [
        'Invoice No,Date,Client Name,Amount,Due Date',
        'HT-IMP-001,01/07/2026,Acme Corp,50000,31/07/2026',
        'HT-IMP-002,05/07/2026,Beta Solutions,118000,',
        'HT-IMP-003,10/07/2026,Unknown Client,20000,',  // should be skipped
    ]);

    $file = UploadedFile::fake()->createWithContent('invoices.csv', $csv);

    $response = $this->actingAs($this->accounts)
        ->post(route('invoices.import.store'), ['csv' => $file])
        ->assertRedirect(route('invoices.index'));

    expect(Invoice::where('invoice_number', 'HT-IMP-001')->exists())->toBeTrue()
        ->and(Invoice::where('invoice_number', 'HT-IMP-002')->exists())->toBeTrue()
        ->and(Invoice::where('invoice_number', 'HT-IMP-003')->exists())->toBeFalse();

    expect(session('status'))->toContain('2 invoice(s) imported')
        ->and(session('status'))->toContain('skipped');
});

it('skips duplicate invoice numbers on import', function () {
    Invoice::factory()->create(['invoice_number' => 'HT-DUP-001', 'customer_id' => $this->customer->id]);

    $csv = implode("\n", [
        'Invoice No,Date,Client Name,Amount',
        'HT-DUP-001,01/07/2026,Acme Corp,50000',
    ]);

    $file = UploadedFile::fake()->createWithContent('invoices.csv', $csv);

    $this->actingAs($this->accounts)
        ->post(route('invoices.import.store'), ['csv' => $file])
        ->assertRedirect(route('invoices.index'));

    expect(Invoice::where('invoice_number', 'HT-DUP-001')->count())->toBe(1);
});

it('rejects a missing required CSV column', function () {
    $csv = "Invoice No,Date\nHT-001,01/07/2026\n";
    $file = UploadedFile::fake()->createWithContent('invoices.csv', $csv);

    $this->actingAs($this->accounts)
        ->post(route('invoices.import.store'), ['csv' => $file])
        ->assertSessionHasErrors('csv');
});

it('show page renders with deal and project links', function () {
    $deal = Deal::factory()->create(['customer_id' => $this->customer->id]);
    $project = Project::factory()->create(['customer_id' => $this->customer->id]);

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'deal_id' => $deal->id,
        'project_id' => $project->id,
        'total' => 5000000,
    ]);

    $this->actingAs($this->accounts)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee($deal->title)
        ->assertSee($project->name);
});

it('offers a Download PDF link on the invoice show page', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->accounts)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Download PDF')
        ->assertSee(route('invoices.pdf', $invoice), false);
});

it('offers a Back link to the invoices index on the invoice show page', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->accounts)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Back')
        ->assertSee(route('invoices.index'), false);
});
