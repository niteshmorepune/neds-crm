<?php

use App\Enums\VisibilityAuditTier;
use App\Jobs\RecordVisibilityAuditPurchase;
use App\Jobs\SendVisibilityAuditPaymentConfirmationJob;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.visibility_audit_payment_template_name' => 'visibility_audit_payment_received',
    ]);
});

function visibilityAuditPurchase(array $overrides = []): VisibilityAuditPurchase
{
    return VisibilityAuditPurchase::create(array_merge([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_va_test1',
        'razorpay_order_id' => 'order_va_test1',
        'payer_name' => 'Priya Shah',
        'payer_phone' => '+91 98765 43210',
        'payer_email' => 'priya@shah.test',
    ], $overrides));
}

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the normalized phone, marketing businessNumber, template name, and name/tier variables to wadesk.in', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $purchase = visibilityAuditPurchase();

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request->header('X-Service-Key')[0] === 'wadesk-secret'
            && $request['phone'] === '919876543210'
            && $request['businessNumber'] === '919112095202'
            && $request['templateName'] === 'visibility_audit_payment_received'
            && $request['variables'] === ['Priya Shah', 'GBP Audit (₹120.00)'];
    });
});

it('falls back to a generic greeting when the payer has no name', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $purchase = visibilityAuditPurchase(['payer_name' => null]);

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertSent(fn ($request) => $request['variables'][0] === 'there');
});

it('falls back to a generic tier label when the amount matched no known tier', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $purchase = visibilityAuditPurchase(['tier' => null, 'amount_paise' => 99999]);

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertSent(fn ($request) => $request['variables'][1] === 'Visibility Audit (₹999.99)');
});

it('skips the HTTP call when the template name is not configured', function () {
    Http::fake();
    config(['services.wadesk.visibility_audit_payment_template_name' => null]);

    $purchase = visibilityAuditPurchase();

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the marketing number is not configured', function () {
    Http::fake();
    config(['services.wadesk.marketing_number' => null]);

    $purchase = visibilityAuditPurchase();

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when wadesk config is not set', function () {
    Http::fake();
    config(['services.wadesk.service_key' => null]);

    $purchase = visibilityAuditPurchase();

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the purchase has no payer phone', function () {
    Http::fake();

    $purchase = visibilityAuditPurchase(['payer_phone' => null]);

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the purchase no longer exists', function () {
    Http::fake();

    (new SendVisibilityAuditPaymentConfirmationJob(999999))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in returns an error', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'Not found'], 404)]);

    $purchase = visibilityAuditPurchase();

    expect(fn () => (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $purchase = visibilityAuditPurchase();

    expect(fn () => (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Dispatch behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches SendVisibilityAuditPaymentConfirmationJob when RecordVisibilityAuditPurchase records a purchase', function () {
    Queue::fake();

    (new RecordVisibilityAuditPurchase(
        paymentId: 'pay_va_test2',
        orderId: 'order_va_test2',
        amountPaise: 12000,
        phone: '+919876543210',
        email: 'priya@shah.test',
        name: 'Priya Shah',
    ))->handle();

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test2')->first();

    Queue::assertPushed(
        SendVisibilityAuditPaymentConfirmationJob::class,
        fn ($job) => $job->purchaseId === $purchase->id,
    );
});
