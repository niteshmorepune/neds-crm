<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\PaymentPromiseBroken;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->accounts = User::factory()->role(UserRole::Accounts)->create();
});

it('notifies accounts staff when a payment promise has been broken', function () {
    Notification::fake();
    $client = Customer::factory()->create(['company_name' => 'Broken Promise Co']);
    $invoice = Invoice::factory()->create([
        'customer_id' => $client->id,
        'total' => 100000,
        'amount_paid' => 0,
        'payment_promised_date' => now()->subDays(2),
    ]);

    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();

    Notification::assertSentTo($this->accounts, PaymentPromiseBroken::class, fn ($n) => $n->invoice->is($invoice));
    expect($invoice->fresh()->payment_promise_notified_for->toDateString())
        ->toBe($invoice->payment_promised_date->toDateString());
});

it('does not notify when the promised date is still in the future', function () {
    Notification::fake();
    Invoice::factory()->create([
        'total' => 100000,
        'amount_paid' => 0,
        'payment_promised_date' => now()->addDays(3),
    ]);

    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not notify when the invoice is already fully paid', function () {
    Notification::fake();
    Invoice::factory()->create([
        'total' => 100000,
        'amount_paid' => 100000,
        'payment_promised_date' => now()->subDays(2),
    ]);

    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not re-notify for the same broken promise on a later run', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create([
        'total' => 100000,
        'amount_paid' => 0,
        'payment_promised_date' => now()->subDays(2),
    ]);

    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();
    Notification::assertSentTo($this->accounts, PaymentPromiseBroken::class);
    Notification::fake(); // reset the recorded sends for the second run

    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('notifies again once staff record a new, later promise that also breaks', function () {
    Notification::fake();
    $invoice = Invoice::factory()->create([
        'total' => 100000,
        'amount_paid' => 0,
        'payment_promised_date' => now()->subDays(5),
    ]);
    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();
    Notification::fake();

    // Staff logs a fresh promise; it later breaks too.
    $invoice->update(['payment_promised_date' => now()->subDay()]);
    $this->artisan('app:send-payment-promise-reminders')->assertSuccessful();

    Notification::assertSentTo($this->accounts, PaymentPromiseBroken::class, fn ($n) => $n->invoice->is($invoice));
});
