<?php

use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchType;
use App\Jobs\RecordVisibilityAuditPurchase;
use App\Jobs\SendVisibilityAuditPaymentConfirmationJob;
use App\Jobs\SendVisibilityAuditPaymentReceiptEmailJob;
use App\Mail\VisibilityAuditPaymentReceived;
use App\Models\Lead;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

it('logs a successful touch against the matched lead', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = Lead::factory()->create();
    $purchase = visibilityAuditPurchase(['lead_id' => $lead->id]);

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::PaymentConfirmation)
        ->and($touch->success)->toBeTrue();
});

it('logs no touch for an anonymous purchase with no matched lead', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $purchase = visibilityAuditPurchase(['lead_id' => null]);

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    expect(VisibilityAuditTouch::count())->toBe(0);
});

it('logs a failed touch when wadesk.in returns an error, for a lead-matched purchase', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'Not found'], 404)]);

    $lead = Lead::factory()->create();
    $purchase = visibilityAuditPurchase(['lead_id' => $lead->id]);

    (new SendVisibilityAuditPaymentConfirmationJob($purchase->id))->handle();

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)->and($touch->success)->toBeFalse();
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

it('dispatches SendVisibilityAuditPaymentReceiptEmailJob when RecordVisibilityAuditPurchase records a purchase', function () {
    Queue::fake();

    (new RecordVisibilityAuditPurchase(
        paymentId: 'pay_va_test3',
        orderId: 'order_va_test3',
        amountPaise: 12000,
        phone: '+919876543210',
        email: 'priya@shah.test',
        name: 'Priya Shah',
    ))->handle();

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test3')->first();

    Queue::assertPushed(
        SendVisibilityAuditPaymentReceiptEmailJob::class,
        fn ($job) => $job->purchaseId === $purchase->id,
    );
});

// ──────────────────────────────────────────────────────────────────────────────
// SendVisibilityAuditPaymentReceiptEmailJob (the email half of the thank-you)
// ──────────────────────────────────────────────────────────────────────────────

it('sends the payment-received email to the payer', function () {
    Mail::fake();

    $purchase = visibilityAuditPurchase();

    (new SendVisibilityAuditPaymentReceiptEmailJob($purchase->id))->handle();

    Mail::assertSent(VisibilityAuditPaymentReceived::class, function ($mail) use ($purchase) {
        return $mail->hasTo($purchase->payer_email)
            && $mail->purchase->is($purchase);
    });
});

it('skips sending the email when the purchase has no payer email', function () {
    Mail::fake();

    $purchase = visibilityAuditPurchase(['payer_email' => null]);

    (new SendVisibilityAuditPaymentReceiptEmailJob($purchase->id))->handle();

    Mail::assertNothingSent();
});

it('skips sending the email when the purchase no longer exists', function () {
    Mail::fake();

    (new SendVisibilityAuditPaymentReceiptEmailJob(999999))->handle();

    Mail::assertNothingSent();
});

it('logs a warning but does not throw when sending the payment-received email fails', function () {
    Mail::shouldReceive('to')->andThrow(new Exception('SMTP down'));

    $purchase = visibilityAuditPurchase();

    expect(fn () => (new SendVisibilityAuditPaymentReceiptEmailJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});

it('renders the correct subject, amount, tier, and payment reference in the email', function () {
    $purchase = visibilityAuditPurchase();

    $mailable = new VisibilityAuditPaymentReceived($purchase);

    expect($mailable->envelope()->subject)->toBe('Payment received — '.config('company.name'));

    $rendered = $mailable->render();
    expect($rendered)
        ->toContain('Priya Shah')
        ->toContain('₹120.00')
        ->toContain('GBP Audit')
        ->toContain('pay_va_test1');
});
