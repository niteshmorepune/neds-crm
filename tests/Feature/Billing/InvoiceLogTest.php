<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
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
