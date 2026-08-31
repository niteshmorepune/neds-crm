<?php

use App\Enums\ClientAdvanceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Models\ClientAdvance;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRecordedNotification;
use App\Services\DashboardMetrics;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->accounts = User::factory()->role(UserRole::Accounts)->create();
    $this->sales = User::factory()->role(UserRole::Sales)->create();
    $this->owner = User::factory()->role(UserRole::Sales)->create();
    $this->customer = Customer::factory()->create(['owner_id' => $this->owner->id]);
});

it('lets Accounts record an advance against a customer with zero deals or invoices', function () {
    $this->actingAs($this->accounts)
        ->post(route('advances.store', $this->customer), [
            'amount' => 50000,
            'received_on' => now()->toDateString(),
            'mode' => PaymentMode::Neft->value,
            'reference' => 'UTR123456',
        ])
        ->assertRedirect(route('clients.show', $this->customer));

    $advance = ClientAdvance::first();
    expect($advance)->not->toBeNull()
        ->and($advance->customer_id)->toBe($this->customer->id)
        ->and($advance->amount)->toBe(5000000)
        ->and($advance->status)->toBe(ClientAdvanceStatus::Outstanding)
        ->and($advance->remaining())->toBe(5000000);

    expect($this->owner->fresh()->notifications()->count())->toBe(1);
});

it('blocks Sales from recording an advance', function () {
    $this->actingAs($this->sales)
        ->post(route('advances.store', $this->customer), [
            'amount' => 50000,
            'received_on' => now()->toDateString(),
            'mode' => PaymentMode::Cash->value,
        ])
        ->assertForbidden();

    expect(ClientAdvance::count())->toBe(0);
});

it('applies an advance to an invoice, creating a real Payment and refreshing both balances', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 5000000, // ₹50,000
        'received_on' => now(),
        'mode' => PaymentMode::Upi,
        'reference' => 'UPI999',
        'recorded_by' => $this->accounts->id,
    ]);
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create([
        'customer_id' => $this->customer->id,
        'total' => 3000000, // ₹30,000
    ]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.advances.apply', [$invoice, $advance]), ['amount' => 30000])
        ->assertRedirect(route('invoices.show', $invoice));

    $payment = Payment::where('client_advance_id', $advance->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe(3000000)
        ->and($payment->mode)->toBe(PaymentMode::Upi)
        ->and($payment->invoice_id)->toBe($invoice->id);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->amount_paid)->toBe(3000000);

    $advance->refresh();
    expect($advance->amount_applied)->toBe(3000000)
        ->and($advance->remaining())->toBe(2000000)
        ->and($advance->status)->toBe(ClientAdvanceStatus::PartiallyApplied);

    expect($this->owner->fresh()->notifications()->where('type', PaymentRecordedNotification::class)->exists())->toBeTrue();
});

it('rejects applying more than the advance remaining even if a larger amount is posted directly', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 1000000, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'recorded_by' => $this->accounts->id,
    ]);
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 5000000]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.advances.apply', [$invoice, $advance]), ['amount' => 20000])
        ->assertSessionHasErrors('amount');

    expect(Payment::count())->toBe(0);
});

it('rejects applying more than the invoice balance even if the advance has more remaining', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 9000000, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'recorded_by' => $this->accounts->id,
    ]);
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 1000000]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.advances.apply', [$invoice, $advance]), ['amount' => 50000])
        ->assertSessionHasErrors('amount');

    expect(Payment::count())->toBe(0);
});

it('404s applying an advance to another customer\'s invoice', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 1000000, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'recorded_by' => $this->accounts->id,
    ]);
    $otherInvoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['total' => 1000000]);

    $this->actingAs($this->accounts)
        ->post(route('invoices.advances.apply', [$otherInvoice, $advance]), ['amount' => 5000])
        ->assertNotFound();
});

it('blocks cancelling an advance that already has payments applied', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 1000000, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'recorded_by' => $this->accounts->id,
    ]);
    $invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => $this->customer->id, 'total' => 1000000]);
    $this->actingAs($this->accounts)->post(route('invoices.advances.apply', [$invoice, $advance]), ['amount' => 5000]);

    $this->actingAs($this->accounts)
        ->post(route('advances.cancel', $advance))
        ->assertForbidden();

    expect($advance->fresh()->status)->not->toBe(ClientAdvanceStatus::Cancelled);
});

it('lets Accounts cancel an untouched advance', function () {
    $advance = $this->customer->clientAdvances()->create([
        'amount' => 1000000, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'recorded_by' => $this->accounts->id,
    ]);

    $this->actingAs($this->accounts)
        ->post(route('advances.cancel', $advance))
        ->assertRedirect();

    expect($advance->fresh()->status)->toBe(ClientAdvanceStatus::Cancelled)
        ->and($advance->fresh()->remaining())->toBe(0);
});

it('sums unapplied advances correctly for the dashboard', function () {
    $this->customer->clientAdvances()->create(['amount' => 1000000, 'received_on' => now(), 'mode' => PaymentMode::Cash]);
    $other = Customer::factory()->create();
    $other->clientAdvances()->create(['amount' => 500000, 'received_on' => now(), 'mode' => PaymentMode::Cash]);
    // A cancelled advance should not count.
    $this->customer->clientAdvances()->create(['amount' => 999999, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'status' => ClientAdvanceStatus::Cancelled]);

    $stats = app(DashboardMetrics::class)->accountsStats();

    expect($stats['unapplied_advances'])->toBe(1500000);
});

it('shows the Unapplied Advances tile on the client page, excluding a cancelled advance', function () {
    $this->customer->clientAdvances()->create(['amount' => 1000000, 'received_on' => now(), 'mode' => PaymentMode::Cash]);
    $this->customer->clientAdvances()->create(['amount' => 999999, 'received_on' => now(), 'mode' => PaymentMode::Cash, 'status' => ClientAdvanceStatus::Cancelled]);

    $this->actingAs($this->accounts)->get(route('clients.show', $this->customer))
        ->assertOk()
        ->assertSee('Unapplied Advances')
        ->assertSee('₹10,000.00')
        ->assertDontSee('₹19,999.99');

    // $this->owner (Sales) can view this client (they own it) but Sales has
    // no ClientAdvance::viewAny access regardless of ownership.
    $this->actingAs($this->owner)->get(route('clients.show', $this->customer))
        ->assertOk()
        ->assertDontSee('Unapplied Advances');
});
