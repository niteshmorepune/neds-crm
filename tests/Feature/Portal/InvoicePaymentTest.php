<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMode;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'test-key-secret',
    ]);

    $this->customer = Customer::factory()->create();
    $this->contact = Contact::factory()->portalUser()->create(['customer_id' => $this->customer->id]);
    $this->invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create([
        'customer_id' => $this->customer->id,
        'total' => 100000, // paise
    ]);
});

it('creates a Razorpay order for a payable invoice belonging to the contact\'s own customer', function () {
    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_test123', 'amount' => 100000, 'currency' => 'INR']),
    ]);

    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.order', $this->invoice))
        ->assertOk()
        ->assertJson(['order_id' => 'order_test123', 'amount' => 100000, 'key_id' => 'rzp_test_key']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['amount'] === 100000
        && $request['notes']['invoice_id'] === (string) $this->invoice->id);
});

it('404s creating an order for another customer\'s invoice', function () {
    $other = Invoice::factory()->status(InvoiceStatus::Sent)->create(['customer_id' => Customer::factory()->create()->id, 'total' => 50000]);

    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.order', $other))
        ->assertNotFound();
});

it('refuses to create an order for a Paid invoice', function () {
    $this->invoice->update(['status' => InvoiceStatus::Paid, 'amount_paid' => 100000]);

    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.order', $this->invoice))
        ->assertStatus(422);
});

it('returns 503 when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);

    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.order', $this->invoice))
        ->assertStatus(503);
});

it('verifies a correctly signed payment and records it', function () {
    Http::fake([
        'api.razorpay.com/v1/orders/order_test123' => Http::response([
            'id' => 'order_test123',
            'amount' => 100000,
            'notes' => ['invoice_id' => (string) $this->invoice->id],
        ]),
    ]);

    $signature = hash_hmac('sha256', 'order_test123|pay_test456', 'test-key-secret');

    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.verify', $this->invoice), [
            'razorpay_order_id' => 'order_test123',
            'razorpay_payment_id' => 'pay_test456',
            'razorpay_signature' => $signature,
        ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $payment = Payment::where('gateway_payment_id', 'pay_test456')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->mode)->toBe(PaymentMode::Gateway)
        ->and($payment->amount)->toBe(100000)
        ->and($payment->invoice_id)->toBe($this->invoice->id);
    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('rejects verify with a bad signature and records nothing', function () {
    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.verify', $this->invoice), [
            'razorpay_order_id' => 'order_test123',
            'razorpay_payment_id' => 'pay_test456',
            'razorpay_signature' => 'not-the-real-signature',
        ])
        ->assertStatus(422);

    expect(Payment::count())->toBe(0);
});

it('rejects verify when the fetched order notes point at a different invoice', function () {
    $otherInvoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'total' => 50000]);

    Http::fake([
        'api.razorpay.com/v1/orders/order_test123' => Http::response([
            'id' => 'order_test123',
            'amount' => 100000,
            'notes' => ['invoice_id' => (string) $otherInvoice->id],
        ]),
    ]);

    $signature = hash_hmac('sha256', 'order_test123|pay_test456', 'test-key-secret');

    $this->actingAs($this->contact, 'portal')
        ->postJson(route('portal.invoices.pay.verify', $this->invoice), [
            'razorpay_order_id' => 'order_test123',
            'razorpay_payment_id' => 'pay_test456',
            'razorpay_signature' => $signature,
        ])
        ->assertStatus(422);

    expect(Payment::count())->toBe(0);
});
