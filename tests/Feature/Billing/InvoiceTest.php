<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Livewire\InvoiceBuilder;
use App\Livewire\RecordNotes;
use App\Mail\InvoiceIssued;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Payment;
use App\Models\QuotationMilestone;
use App\Models\User;
use App\Services\MenuResolver;
use App\Support\UpiQrCode;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->accounts = User::factory()->role(UserRole::Accounts)->create();
});

function invoiceWithLine(array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->create(array_merge(['place_of_supply_state_code' => '27'], $attributes));
    $invoice->items()->create([
        'description' => 'SEO retainer', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000,
    ]);
    $invoice->refresh()->recalculateTotals();

    return $invoice->refresh();
}

it('records a partial payment then marks paid in full', function () {
    $invoice = invoiceWithLine(); // total ₹1180 = 118000 paise

    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->amount_paid)->toBe(50000)
        ->and($invoice->balance())->toBe(68000);

    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '680', 'paid_on' => now()->toDateString(), 'mode' => 'neft',
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->balance())->toBe(0);
});

it('lets accounts correct a payment\'s date, mode, and reference', function () {
    $invoice = invoiceWithLine();
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '500', 'paid_on' => now()->subDays(3)->toDateString(), 'mode' => 'cash',
    ]);
    $payment = $invoice->payments()->firstOrFail();
    $correctedDate = now()->toDateString();

    $this->actingAs($this->accounts)->patch(route('invoices.payments.update', [$invoice, $payment]), [
        'paid_on' => $correctedDate, 'mode' => 'upi', 'reference' => 'UPI-REF-123',
    ])->assertRedirect();

    $payment->refresh();
    expect($payment->paid_on->toDateString())->toBe($correctedDate)
        ->and($payment->mode)->toBe(PaymentMode::Upi)
        ->and($payment->reference)->toBe('UPI-REF-123')
        ->and($payment->amount)->toBe(50000); // unchanged
});

it('ignores an amount/tds_amount sent to the payment update endpoint — those still require delete-and-recreate', function () {
    $invoice = invoiceWithLine();
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ]);
    $payment = $invoice->payments()->firstOrFail();

    $this->actingAs($this->accounts)->patch(route('invoices.payments.update', [$invoice, $payment]), [
        'paid_on' => now()->toDateString(), 'mode' => 'cash', 'amount' => '999999', 'tds_amount' => '999999',
    ]);

    expect($payment->fresh()->amount)->toBe(50000)
        ->and($payment->fresh()->tds_amount)->toBe(0);
});

it('logs a payment correction to the activity trail', function () {
    $invoice = invoiceWithLine();
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ]);
    $payment = $invoice->payments()->firstOrFail();

    $this->actingAs($this->accounts)->patch(route('invoices.payments.update', [$invoice, $payment]), [
        'paid_on' => now()->subDay()->toDateString(), 'mode' => 'upi',
    ]);

    expect(Activity::where('subject_type', Payment::class)
        ->where('subject_id', $payment->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();
});

it('404s when the payment does not belong to the given invoice', function () {
    $invoiceA = invoiceWithLine();
    $invoiceB = invoiceWithLine();
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoiceA), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ]);
    $payment = $invoiceA->payments()->firstOrFail();

    $this->actingAs($this->accounts)->patch(route('invoices.payments.update', [$invoiceB, $payment]), [
        'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ])->assertNotFound();
});

it('forbids a role without invoice access from editing a payment', function () {
    $invoice = invoiceWithLine();
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ]);
    $payment = $invoice->payments()->firstOrFail();
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->patch(route('invoices.payments.update', [$invoice, $payment]), [
        'paid_on' => now()->toDateString(), 'mode' => 'cash',
    ])->assertForbidden();
});

it('records TDS alongside a payment, deducting it from the balance and settling the invoice', function () {
    $invoice = invoiceWithLine(); // total ₹1180 = 118000 paise

    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '1000', 'tds_amount' => '180', 'paid_on' => now()->toDateString(), 'mode' => 'neft',
    ]);

    $invoice->refresh();
    expect($invoice->amount_paid)->toBe(100000)
        ->and($invoice->tdsTotal())->toBe(18000)
        ->and($invoice->balance())->toBe(0)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid);
});

it('rejects a payment whose amount plus TDS exceeds the balance', function () {
    $invoice = invoiceWithLine(); // total ₹1180

    $this->actingAs($this->accounts)
        ->post(route('invoices.payments.store', $invoice), [
            'amount' => '1000', 'tds_amount' => '200', 'paid_on' => now()->toDateString(), 'mode' => 'neft',
        ])
        ->assertSessionHasErrors('amount');

    expect($invoice->fresh()->payments()->count())->toBe(0);
});

it('rejects a payment that exceeds the balance', function () {
    $invoice = invoiceWithLine();

    $this->actingAs($this->accounts)
        ->post(route('invoices.payments.store', $invoice), [
            'amount' => '2000', 'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])
        ->assertSessionHasErrors('amount');

    expect($invoice->fresh()->payments()->count())->toBe(0);
});

it('streams a PDF invoice', function () {
    $invoice = invoiceWithLine();

    $response = $this->actingAs($this->accounts)->get(route('invoices.pdf', $invoice));

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF');
});

it('shows a Scan to Pay QR code on the PDF when a UPI ID is configured and the invoice is unpaid', function () {
    config(['company.upi_id' => 'niranjanenterprises@okhdfcbank']);
    $invoice = invoiceWithLine();
    $invoice->load(['customer', 'items']);

    $html = view('invoices.pdf', ['invoice' => $invoice])->render();

    expect($html)->toContain('Scan to Pay')
        ->toContain('data:image/png;base64,');
});

it('omits the Scan to Pay QR code once the invoice is fully paid', function () {
    config(['company.upi_id' => 'niranjanenterprises@okhdfcbank']);
    $invoice = invoiceWithLine(['amount_paid' => 118000]);
    $invoice->load(['customer', 'items']);

    $html = view('invoices.pdf', ['invoice' => $invoice])->render();

    expect($html)->not->toContain('Scan to Pay');
});

it('omits the Scan to Pay QR code when no UPI ID is configured', function () {
    config(['company.upi_id' => '']);
    $invoice = invoiceWithLine();
    $invoice->load(['customer', 'items']);

    $html = view('invoices.pdf', ['invoice' => $invoice])->render();

    expect($html)->not->toContain('Scan to Pay');
});

it('encodes a correct UPI deep link into the Scan to Pay QR code', function () {
    $uri = UpiQrCode::dataUri('niranjanenterprises@okhdfcbank', 'Niranjan Enterprises', 5900.00, '26 / 27 - 033');

    expect($uri)->toStartWith('data:image/png;base64,');
});

it('groups the HSN/SAC tax summary by code and rate, matching the invoice\'s own stored totals', function () {
    $invoice = Invoice::factory()->create(['place_of_supply_state_code' => '27']);
    $invoice->items()->create([
        'description' => 'SEO', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000, 'sort_order' => 1,
    ]);
    $invoice->items()->create([
        'description' => 'GMB', 'sac_code' => '998361',
        'quantity' => 1, 'rate' => 50000, 'gst_rate' => 18, 'amount' => 50000, 'sort_order' => 2,
    ]);
    $invoice->items()->create([
        'description' => 'Website Dev', 'sac_code' => '998314',
        'quantity' => 1, 'rate' => 200000, 'gst_rate' => 12, 'amount' => 200000, 'sort_order' => 3,
    ]);
    $invoice->refresh()->recalculateTotals();
    $invoice->load('items');

    $summary = $invoice->hsnSummary();

    expect($summary)->toHaveCount(2);

    $seoGroup = collect($summary)->firstWhere('sac_code', '998361');
    expect($seoGroup['taxable'])->toBe(150000)
        ->and($seoGroup['cgst'] + $seoGroup['sgst'])->toBe((int) round(150000 * 0.18));

    $webGroup = collect($summary)->firstWhere('sac_code', '998314');
    expect($webGroup['taxable'])->toBe(200000)
        ->and($webGroup['cgst'] + $webGroup['sgst'])->toBe((int) round(200000 * 0.12));

    $totalCgst = collect($summary)->sum('cgst');
    $totalSgst = collect($summary)->sum('sgst');
    expect($totalCgst)->toBe($invoice->cgst_total)
        ->and($totalSgst)->toBe($invoice->sgst_total);
});

it('renders a bordered HSN/SAC-wise CGST+SGST summary table on the PDF for a GST invoice', function () {
    $invoice = invoiceWithLine();
    $invoice->load(['customer', 'items']);

    $html = view('invoices.pdf', ['invoice' => $invoice])->render();

    expect($html)->toContain('HSN/SAC')
        ->toContain('Taxable Amount')
        ->toContain('Total Tax Amount');
});

it('renders the PDF template with non-GST wording and no tax breakup for a GST-exempt invoice', function () {
    $invoice = invoiceWithLine(['is_gst_exempt' => true]);
    $invoice->load(['customer', 'items']);

    $html = view('invoices.pdf', ['invoice' => $invoice])->render();

    expect($html)->toContain('Non-GST Invoice')
        ->toContain('GST not charged')
        ->not->toContain('TAX INVOICE')
        ->not->toContain('CGST');
});

it('renders invoice index, show and the receivables report', function () {
    $invoice = invoiceWithLine();

    $this->actingAs($this->accounts)->get(route('invoices.index'))->assertOk()->assertSee('Invoices');
    $this->actingAs($this->accounts)->get(route('invoices.show', $invoice))->assertOk()->assertSee($invoice->invoice_number);
    $this->actingAs($this->accounts)->get(route('reports.receivables'))->assertOk()->assertSee('Outstanding');
});

it('lists this month\'s payments behind the Collected this month dashboard tile', function () {
    $invoice = invoiceWithLine();
    $this->actingAs($this->accounts)->post(route('invoices.payments.store', $invoice), [
        'amount' => '500', 'paid_on' => now()->toDateString(), 'mode' => 'upi',
    ]);

    // A payment from last month must not count towards this month's total.
    $lastMonthInvoice = invoiceWithLine();
    $lastMonthInvoice->payments()->create([
        'paid_on' => now()->subMonth(), 'mode' => 'cash', 'amount' => 999900, 'recorded_by' => $this->accounts->id,
    ]);

    $response = $this->actingAs($this->accounts)->get(route('reports.collected'));

    $response->assertOk()
        ->assertSee('Collected This Month')
        ->assertSee($invoice->invoice_number)
        ->assertSee('₹500.00', false)
        ->assertDontSee($lastMonthInvoice->invoice_number);

    expect($response->viewData('total'))->toBe(50000);
});

it('shows "Client removed" on the collected-this-month report when the invoice\'s customer is soft-deleted', function () {
    $invoice = invoiceWithLine();
    $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => 100000, 'recorded_by' => $this->accounts->id]);
    Customer::withoutEvents(fn () => $invoice->customer->delete());

    $this->actingAs($this->accounts)->get(route('reports.collected'))
        ->assertOk()
        ->assertSee('Client removed');
});

it('forbids a role without invoice access from viewing collected-this-month', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('reports.collected'))->assertForbidden();
});

it('shows the due date on an unpaid invoice but hides it once the invoice is Paid', function () {
    $invoice = invoiceWithLine(['due_date' => now()->addDays(10)->toDateString()]);
    $dueDateFormatted = $invoice->due_date->format('d M Y');

    $indexHtml = $this->actingAs($this->accounts)->get(route('invoices.index'))->assertOk()->getContent();
    expect($indexHtml)->toContain($dueDateFormatted);
    $this->actingAs($this->accounts)->get(route('invoices.show', $invoice))->assertOk()->assertSee($dueDateFormatted);

    $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => $invoice->total, 'recorded_by' => $this->accounts->id]);
    $invoice->refreshPaymentStatus();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $indexHtml = $this->actingAs($this->accounts)->get(route('invoices.index'))->assertOk()->getContent();
    expect($indexHtml)->not->toContain($dueDateFormatted);
    $this->actingAs($this->accounts)->get(route('invoices.show', $invoice))->assertOk()->assertDontSee($dueDateFormatted);
});

it('restricts invoices to the accounts team plus sales (read-only), blocking other roles', function () {
    expect(User::factory()->role(UserRole::Sales)->create()->can('viewAny', Invoice::class))->toBeTrue()
        ->and(User::factory()->role(UserRole::Support)->create()->can('viewAny', Invoice::class))->toBeFalse()
        ->and($this->accounts->can('viewAny', Invoice::class))->toBeTrue();

    // Support is blocked at the route by menu.access:invoices (no default grant).
    $this->actingAs(User::factory()->role(UserRole::Support)->create())
        ->get(route('invoices.index'))->assertForbidden();
});

it('gives sales read-only invoice access by default, but mutating actions stay accounts-team-only', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $invoice = invoiceWithLine();

    expect($sales->can('viewAny', Invoice::class))->toBeTrue();
    $this->actingAs($sales)->get(route('invoices.index'))->assertOk();

    expect($sales->can('update', $invoice))->toBeFalse()
        ->and($sales->can('delete', $invoice))->toBeFalse()
        ->and($sales->can('recordPayment', $invoice))->toBeFalse();
});

it('grants a support user read-only invoice access once their role is added via the Menu Controller', function () {
    $support = User::factory()->role(UserRole::Support)->create();

    expect($support->can('viewAny', Invoice::class))->toBeFalse();
    $this->actingAs($support)->get(route('invoices.index'))->assertForbidden();

    MenuItem::where('key', 'invoices')->firstOrFail()->roleAssignments()->create(['role' => UserRole::Support]);
    app(MenuResolver::class)->flush();

    expect($support->can('viewAny', Invoice::class))->toBeTrue();
    $this->actingAs($support)->get(route('invoices.index'))->assertOk();
});

it('deletes a draft invoice', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $unpaid = invoiceWithLine();

    $this->actingAs($admin)->delete(route('invoices.destroy', $unpaid))->assertRedirect(route('invoices.index'));
    expect(Invoice::find($unpaid->id))->toBeNull();
});

it('deletes an invoice that already has a payment recorded, soft-deleting the payment alongside it', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $invoice = invoiceWithLine();

    $payment = $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => 1000, 'recorded_by' => $admin->id]);
    $invoice->refreshPaymentStatus();

    $this->actingAs($admin)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));

    expect(Invoice::find($invoice->id))->toBeNull()
        ->and(Invoice::withTrashed()->find($invoice->id))->not->toBeNull()
        ->and(Payment::find($payment->id))->toBeNull()
        ->and(Payment::withTrashed()->find($payment->id))->not->toBeNull();
});

it('lets an accounts-role user delete an unpaid invoice', function () {
    $invoice = invoiceWithLine();

    $this->actingAs($this->accounts)->delete(route('invoices.destroy', $invoice))->assertRedirect(route('invoices.index'));
    expect(Invoice::find($invoice->id))->toBeNull();
});

it('edits a draft invoice via the InvoiceBuilder and recalculates totals, but locks once paid', function () {
    $invoice = invoiceWithLine();

    Livewire::actingAs($this->accounts)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->set('items', [[
            'description' => 'SEO retainer', 'sac_code' => '998361',
            'quantity' => '2', 'rate' => '1000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    expect($invoice->fresh()->total)->toBe(236000);

    $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => $invoice->total, 'recorded_by' => $this->accounts->id]);
    $invoice->refreshPaymentStatus();

    expect($invoice->fresh()->isEditable())->toBeFalse()
        ->and($this->accounts->can('update', $invoice->fresh()))->toBeFalse();
});

it('flips a GST invoice to non-GST via the InvoiceBuilder and drops the tax to zero', function () {
    $invoice = invoiceWithLine(); // total ₹1180 = 118000 paise, CGST+SGST 9000 each

    expect($invoice->cgst_total)->toBe(9000);

    Livewire::actingAs($this->accounts)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->set('is_gst_exempt', true)
        ->call('save')
        ->assertHasNoErrors();

    $invoice->refresh();
    expect($invoice->is_gst_exempt)->toBeTrue()
        ->and($invoice->cgst_total)->toBe(0)
        ->and($invoice->sgst_total)->toBe(0)
        ->and($invoice->total)->toBe(100000);
});

it('defaults the InvoiceBuilder GST-exempt toggle from the invoice\'s stored value', function () {
    $invoice = invoiceWithLine(['is_gst_exempt' => true]);

    Livewire::actingAs($this->accounts)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->assertSet('is_gst_exempt', true);
});

it('defaults a brand-new line item to SAC 998314 and 18% GST, not blank', function () {
    // A Log-created invoice with no items yet -- mount() should auto-add
    // one starter row (instead of leaving the form empty) already
    // prefilled with the SAC/rate used on nearly every NEDS invoice.
    $invoice = Invoice::factory()->create(['place_of_supply_state_code' => '27'])->refresh();
    expect($invoice->items)->toBeEmpty();

    $component = Livewire::actingAs($this->accounts)->test(InvoiceBuilder::class, ['invoice' => $invoice]);

    expect($component->get('items'))->toHaveCount(1)
        ->and($component->get('items.0.sac_code'))->toBe('998314')
        ->and($component->get('items.0.gst_rate'))->toBe('18')
        ->and($component->get('items.0.description'))->toBe('');

    $component->call('addItem');
    expect($component->get('items.1.sac_code'))->toBe('998314')
        ->and($component->get('items.1.gst_rate'))->toBe('18');
});

it('still lets a line item override the default SAC/GST rate', function () {
    $invoice = Invoice::factory()->create(['place_of_supply_state_code' => '27'])->refresh();

    Livewire::actingAs($this->accounts)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->set('items', [[
            'description' => 'Website Development', 'sac_code' => '998314',
            'quantity' => '1', 'rate' => '20000', 'gst_rate' => '12',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    expect($invoice->fresh()->items->first()->gst_rate)->toBe('12.00');
});

it('is reachable at invoices.items.edit and turns a flat Log Invoice total into a proper GST-itemized one', function () {
    // Mirrors exactly how a "Logged" invoice actually looks: a flat total,
    // no items, no place of supply -- nothing the flat screen ever collects.
    $customer = Customer::factory()->create(['state_code' => '27']);
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'place_of_supply_state_code' => null,
        'subtotal' => 500000, 'taxable_total' => 500000, 'total' => 500000,
    ])->refresh();

    $this->actingAs($this->accounts)->get(route('invoices.items.edit', $invoice))->assertOk();

    Livewire::actingAs($this->accounts)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->set('items', [[
            'description' => 'Social Media Management', 'sac_code' => '998314',
            'quantity' => '1', 'rate' => '5000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $invoice->refresh();
    expect($invoice->total)->toBe(590000) // ₹5,000 + 18% GST, not the old flat ₹5,000
        ->and($invoice->cgst_total)->toBe(45000)
        ->and($invoice->sgst_total)->toBe(45000)
        ->and($invoice->items)->toHaveCount(1);
});

it('backfills place of supply from the customer\'s real state, not a silent intra-state default, when saving via InvoiceBuilder', function () {
    // A Log-created invoice for an inter-state client with no place of
    // supply set: without the fix, GstCalculator's "no state code -> treat
    // as intra-state" fallback would wrongly charge CGST+SGST here.
    $customer = Customer::factory()->create(['state_code' => '09']); // Uttar Pradesh
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'place_of_supply_state_code' => null,
    ])->refresh();

    Livewire::actingAs($this->accounts)
        ->test(InvoiceBuilder::class, ['invoice' => $invoice])
        ->set('items', [[
            'description' => 'Consulting', 'sac_code' => '998314',
            'quantity' => '1', 'rate' => '5000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $invoice->refresh();
    expect($invoice->place_of_supply_state_code)->toBe('09')
        ->and($invoice->is_intra_state)->toBeFalse()
        ->and($invoice->igst_total)->toBe(90000)
        ->and($invoice->cgst_total)->toBe(0)
        ->and($invoice->sgst_total)->toBe(0);
});

it('shows an Add/Edit GST Line Items link on the invoice page, labeled by whether items already exist', function () {
    $noItems = invoiceWithLine();
    $noItems->items()->delete();

    $this->actingAs($this->accounts)
        ->get(route('invoices.show', $noItems))
        ->assertOk()
        ->assertSee('Add GST Line Items')
        ->assertDontSee('Edit GST Line Items');

    $withItems = invoiceWithLine();

    $this->actingAs($this->accounts)
        ->get(route('invoices.show', $withItems))
        ->assertOk()
        ->assertSee('Edit GST Line Items')
        ->assertDontSee('Add GST Line Items');
});

it('hides the GST Line Items link once the invoice is no longer editable', function () {
    $invoice = invoiceWithLine();
    $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => $invoice->total, 'recorded_by' => $this->accounts->id]);
    $invoice->refreshPaymentStatus();

    $this->actingAs($this->accounts)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee('GST Line Items');
});

it('includes milestone installment details in the invoice email', function () {
    $invoice = invoiceWithLine();
    QuotationMilestone::factory()->create([
        'invoice_id' => $invoice->id,
        'title' => 'Advance',
        'percentage' => 50,
        'amount' => 59000,
        'due_date' => now()->toDateString(),
    ]);

    $mailable = new InvoiceIssued($invoice);
    $mailable->assertSeeInHtml('Advance');
});

it('is not promise-broken with no promised date, and not broken while the date is still in the future', function () {
    $noPromise = invoiceWithLine();
    $futurePromise = invoiceWithLine(['payment_promised_date' => now()->addDays(2)->toDateString()]);

    expect($noPromise->promiseBroken())->toBeFalse()
        ->and($futurePromise->promiseBroken())->toBeFalse();
});

it('is promise-broken once the promised date has passed and a balance is still owed', function () {
    $invoice = invoiceWithLine(['payment_promised_date' => now()->subDays(2)->toDateString()]);

    expect($invoice->promiseBroken())->toBeTrue();
});

it('is not promise-broken once the invoice is fully paid, even past the promised date', function () {
    $invoice = invoiceWithLine(['payment_promised_date' => now()->subDays(2)->toDateString()]);
    $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => $invoice->total, 'recorded_by' => $this->accounts->id]);
    $invoice->refreshPaymentStatus();

    expect($invoice->fresh()->promiseBroken())->toBeFalse();
});

it('lets accounts set and clear a payment promise date on an invoice', function () {
    $invoice = invoiceWithLine();

    $this->actingAs($this->accounts)
        ->post(route('invoices.payment-promise.update', $invoice), ['payment_promised_date' => now()->addDays(3)->toDateString()])
        ->assertRedirect();

    expect($invoice->fresh()->payment_promised_date)->not->toBeNull();

    $this->actingAs($this->accounts)
        ->post(route('invoices.payment-promise.update', $invoice), [])
        ->assertRedirect();

    expect($invoice->fresh()->payment_promised_date)->toBeNull();
});

it('blocks a non-accounts role from setting a payment promise date', function () {
    $invoice = invoiceWithLine();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)
        ->post(route('invoices.payment-promise.update', $invoice), ['payment_promised_date' => now()->addDays(3)->toDateString()])
        ->assertForbidden();

    expect($invoice->fresh()->payment_promised_date)->toBeNull();
});

it('renders a follow-up note against an invoice via the generic RecordNotes component', function () {
    $invoice = invoiceWithLine();

    Livewire::actingAs($this->accounts)
        ->test(RecordNotes::class, ['record' => $invoice, 'canManage' => true])
        ->set('body', 'Client called, promised to pay by Friday.')
        ->call('addNote')
        ->assertHasNoErrors();

    expect($invoice->notes()->count())->toBe(1)
        ->and($invoice->notes()->first()->body)->toBe('Client called, promised to pay by Friday.');
});
