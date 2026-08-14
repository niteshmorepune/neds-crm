<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.razorpay.visibility_audit_webhook_secret' => 'test-visibility-audit-secret']);
});

/**
 * Build and send a signed Razorpay webhook request. Computes the HMAC on the
 * exact JSON bytes that will arrive at the middleware.
 */
function visibilityAuditWebhook(array $payload, string $secret = 'test-visibility-audit-secret'): TestResponse
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    return test()->call(
        'POST',
        '/api/webhooks/razorpay/visibility-audit',
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => $signature,
        ],
        $body,
    );
}

function visibilityAuditPayload(array $entityOverrides = []): array
{
    return [
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => array_merge([
                    'id' => 'pay_va_test1',
                    'order_id' => 'order_va_test1',
                    'amount' => 12000,
                    'contact' => '+919876543210',
                    'email' => 'priya@shah.test',
                    'notes' => [],
                ], $entityOverrides),
            ],
        ],
    ];
}

it('rejects a webhook with no signature header', function () {
    test()->postJson('/api/webhooks/razorpay/visibility-audit', visibilityAuditPayload())
        ->assertStatus(401);
});

it('rejects a webhook with a wrong signature', function () {
    visibilityAuditWebhook(visibilityAuditPayload(), 'wrong-secret')->assertStatus(401);
});

it('does not authenticate with the invoice-payment webhook secret', function () {
    config(['services.razorpay.webhook_secret' => 'invoice-secret']);

    visibilityAuditWebhook(visibilityAuditPayload(), 'invoice-secret')->assertStatus(401);
});

it('attaches a note to an existing open lead matched by phone, for a GBP audit', function () {
    $service = Service::factory()->create(['name' => 'GMB']);
    $lead = Lead::factory()->create([
        'phone' => '9876543210',
        'status' => LeadStatus::New,
        'source' => LeadSource::MetaAds,
        'service_id' => null,
    ]);

    visibilityAuditWebhook(visibilityAuditPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $lead->refresh();
    expect($lead->service_id)->toBe($service->id)
        ->and($lead->notes()->count())->toBe(1)
        ->and($lead->notes()->first()->body)->toContain('₹120')
        ->and($lead->notes()->first()->body)->toContain('GBP Audit');

    expect(Lead::count())->toBe(1);

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->first();
    expect($purchase)->not->toBeNull()
        ->and($purchase->lead_id)->toBe($lead->id)
        ->and($purchase->tier->value)->toBe('gbp')
        ->and($purchase->amount_paise)->toBe(12000);
});

it('creates a new lead when no existing open lead matches the phone', function () {
    visibilityAuditWebhook(visibilityAuditPayload(['amount' => 24000]))
        ->assertOk();

    $lead = Lead::where('phone', '+919876543210')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->email)->toBe('priya@shah.test')
        ->and($lead->source)->toBe(LeadSource::Other)
        ->and($lead->utm_source)->toBe('visibility-audit-offer')
        ->and($lead->notes()->first()->body)->toContain('Website Audit');
});

it('resolves the "both" tier and does not guess a single service', function () {
    Service::factory()->create(['name' => 'GMB']);
    Service::factory()->create(['name' => 'Website Design & Development']);

    visibilityAuditWebhook(visibilityAuditPayload(['amount' => 36000]))->assertOk();

    $lead = Lead::where('phone', '+919876543210')->first();
    expect($lead->service_id)->toBeNull()
        ->and($lead->notes()->first()->body)->toContain('GBP + Website Audit');
});

it('records the purchase even when the amount matches no known tier', function () {
    visibilityAuditWebhook(visibilityAuditPayload(['amount' => 99999]))->assertOk();

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->first();
    expect($purchase)->not->toBeNull()->and($purchase->tier)->toBeNull();

    $lead = Lead::where('phone', '+919876543210')->first();
    expect($lead->notes()->first()->body)->toContain('a Visibility Audit');
});

it('records the purchase but does not create a lead when there is no phone', function () {
    visibilityAuditWebhook(visibilityAuditPayload(['contact' => null]))->assertOk();

    expect(Lead::count())->toBe(0);

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->first();
    expect($purchase)->not->toBeNull()->and($purchase->lead_id)->toBeNull();
});

it('ignores non-payment.captured events', function () {
    visibilityAuditWebhook(['event' => 'order.paid', 'payload' => []])
        ->assertOk()
        ->assertJson(['status' => 'ignored', 'reason' => 'unhandled_event']);

    expect(VisibilityAuditPurchase::count())->toBe(0);
});

it('ignores an event with a payload missing required fields', function () {
    visibilityAuditWebhook([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => ['id' => 'pay_va_test1']]],
    ])->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'missing_fields']);

    expect(VisibilityAuditPurchase::count())->toBe(0);
});

it('is idempotent on a duplicate webhook delivery for the same payment', function () {
    $lead = Lead::factory()->create(['phone' => '9876543210', 'status' => LeadStatus::New]);

    visibilityAuditWebhook(visibilityAuditPayload())->assertOk();
    visibilityAuditWebhook(visibilityAuditPayload())->assertOk();

    expect(VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->count())->toBe(1);
    expect($lead->notes()->count())->toBe(1);
});
