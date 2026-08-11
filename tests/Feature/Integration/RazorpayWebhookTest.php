<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.razorpay.webhook_secret' => 'test-razorpay-webhook-secret']);

    $this->customer = Customer::factory()->create();
    $this->invoice = Invoice::factory()->status(InvoiceStatus::Sent)->create([
        'customer_id' => $this->customer->id,
        'total' => 100000,
    ]);
});

/**
 * Build and send a signed Razorpay webhook request. Computes the HMAC on the
 * exact JSON bytes that will arrive at the middleware.
 */
function razorpayWebhook(array $payload, string $secret = 'test-razorpay-webhook-secret'): TestResponse
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    return test()->call(
        'POST',
        '/api/webhooks/razorpay',
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => $signature,
        ],
        $body,
    );
}

function paymentCapturedPayload(Invoice $invoice, string $orderId = 'order_test123', string $paymentId = 'pay_test456', int $amount = 100000): array
{
    return [
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => [
                    'id' => $paymentId,
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'notes' => ['invoice_id' => (string) $invoice->id],
                ],
            ],
        ],
    ];
}

it('rejects a webhook with no signature header', function () {
    test()->postJson('/api/webhooks/razorpay', paymentCapturedPayload($this->invoice))
        ->assertStatus(401);
});

it('rejects a webhook with a wrong signature', function () {
    razorpayWebhook(paymentCapturedPayload($this->invoice), 'wrong-secret')->assertStatus(401);
});

it('records a payment on a correctly signed payment.captured event', function () {
    razorpayWebhook(paymentCapturedPayload($this->invoice))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $payment = Payment::where('gateway_payment_id', 'pay_test456')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->invoice_id)->toBe($this->invoice->id)
        ->and($payment->amount)->toBe(100000);
    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('ignores non-payment.captured events', function () {
    razorpayWebhook(['event' => 'order.paid', 'payload' => []])
        ->assertOk()
        ->assertJson(['status' => 'ignored', 'reason' => 'unhandled_event']);

    expect(Payment::count())->toBe(0);
});

it('is idempotent on a duplicate webhook delivery for the same payment', function () {
    razorpayWebhook(paymentCapturedPayload($this->invoice))->assertOk();
    razorpayWebhook(paymentCapturedPayload($this->invoice))->assertOk();

    expect(Payment::where('gateway_payment_id', 'pay_test456')->count())->toBe(1);
});

it('ignores an event with a payload missing required fields', function () {
    razorpayWebhook([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['id' => 'pay_test456']]],
    ])->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'missing_fields']);

    expect(Payment::count())->toBe(0);
});
