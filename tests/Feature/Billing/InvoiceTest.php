<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Livewire\InvoiceBuilder;
use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\QuotationMilestone;
use App\Models\User;
use App\Services\MenuResolver;
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

it('deletes a draft invoice but blocks deleting one with a payment', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $invoice = invoiceWithLine();

    $unpaid = invoiceWithLine();
    $this->actingAs($admin)->delete(route('invoices.destroy', $unpaid))->assertRedirect(route('invoices.index'));
    expect(Invoice::find($unpaid->id))->toBeNull();

    $invoice->payments()->create(['paid_on' => now(), 'mode' => 'cash', 'amount' => 1000, 'recorded_by' => $admin->id]);
    $invoice->refreshPaymentStatus();

    $this->actingAs($admin)->delete(route('invoices.destroy', $invoice))->assertForbidden();
    expect(Invoice::find($invoice->id))->not->toBeNull();
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
