<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Enums\UserRole;
use App\Mail\PaymentReceived;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRecordedNotification;
use App\Services\RazorpayPaymentRecorder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->accounts = User::factory()->role(UserRole::Accounts)->create();
    $this->owner = User::factory()->role(UserRole::Sales)->create();
    $this->customer = Customer::factory()->create(['owner_id' => $this->owner->id]);
    Contact::factory()->primary()->create(['customer_id' => $this->customer->id, 'email' => 'billing@client.test']);
    $this->invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create([
        'customer_id' => $this->customer->id,
        'total' => 100000, // paise
    ]);
});

it('records a captured payment and marks the invoice Paid', function () {
    Mail::fake();

    $payment = app(RazorpayPaymentRecorder::class)->record($this->invoice, 'order_1', 'pay_1', 100000);

    expect($payment)->not->toBeNull()
        ->and($payment->mode)->toBe(PaymentMode::Gateway)
        ->and($payment->amount)->toBe(100000)
        ->and($payment->gateway_order_id)->toBe('order_1')
        ->and($payment->gateway_payment_id)->toBe('pay_1')
        ->and($payment->recorded_by)->toBeNull();

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect(Payment::where('gateway_payment_id', 'pay_1')->count())->toBe(1);
});

it('is idempotent on the same gateway_payment_id', function () {
    Mail::fake();
    $recorder = app(RazorpayPaymentRecorder::class);

    $first = $recorder->record($this->invoice, 'order_1', 'pay_1', 100000);
    $second = $recorder->record($this->invoice, 'order_1', 'pay_1', 100000);

    expect(Payment::count())->toBe(1);
    expect($second->id)->toBe($first->id);
});

it('notifies accounts staff and the client owner', function () {
    Mail::fake();

    app(RazorpayPaymentRecorder::class)->record($this->invoice, 'order_1', 'pay_1', 100000);

    expect($this->accounts->fresh()->notifications()->where('type', PaymentRecordedNotification::class)->exists())->toBeTrue();
    expect($this->owner->fresh()->notifications()->where('type', PaymentRecordedNotification::class)->exists())->toBeTrue();
});

it('emails a receipt to the primary contact', function () {
    Mail::fake();

    app(RazorpayPaymentRecorder::class)->record($this->invoice, 'order_1', 'pay_1', 100000);

    Mail::assertSent(PaymentReceived::class, fn ($mail) => $mail->hasTo('billing@client.test'));
});
