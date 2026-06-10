<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

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

it('renders invoice index, show and the receivables report', function () {
    $invoice = invoiceWithLine();

    $this->actingAs($this->accounts)->get(route('invoices.index'))->assertOk()->assertSee('Invoices');
    $this->actingAs($this->accounts)->get(route('invoices.show', $invoice))->assertOk()->assertSee($invoice->invoice_number);
    $this->actingAs($this->accounts)->get(route('reports.receivables'))->assertOk()->assertSee('Outstanding');
});

it('restricts invoices to the accounts team', function () {
    expect(User::factory()->role(UserRole::Sales)->create()->can('viewAny', Invoice::class))->toBeFalse()
        ->and(User::factory()->role(UserRole::Support)->create()->can('viewAny', Invoice::class))->toBeFalse()
        ->and($this->accounts->can('viewAny', Invoice::class))->toBeTrue();

    // Sales is also blocked at the route by menu.access:invoices.
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())
        ->get(route('invoices.index'))->assertForbidden();
});
