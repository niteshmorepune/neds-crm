<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Jobs\ScoreLead;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Support\Facades\Queue;
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

    visibilityAuditWebhook(visibilityAuditPayload([
        'notes' => ['Google Business Profile Link' => 'https://maps.google.com/?cid=123'],
    ]))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $lead->refresh();
    expect($lead->service_id)->toBe($service->id)
        ->and($lead->notes()->count())->toBe(1)
        ->and($lead->notes()->first()->body)->toContain('₹120')
        ->and($lead->notes()->first()->body)->toContain('GBP Audit')
        ->and($lead->notes()->first()->body)->toContain('https://maps.google.com/?cid=123');

    expect(Lead::count())->toBe(1);

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->first();
    expect($purchase)->not->toBeNull()
        ->and($purchase->lead_id)->toBe($lead->id)
        ->and($purchase->tier->value)->toBe('gbp')
        ->and($purchase->amount_paise)->toBe(12000)
        ->and($purchase->gbp_url)->toBe('https://maps.google.com/?cid=123');
});

it('flags a missing GBP link in the note instead of silently omitting it', function () {
    Lead::factory()->create(['phone' => '9876543210', 'status' => LeadStatus::New]);

    visibilityAuditWebhook(visibilityAuditPayload())->assertOk();

    $note = Lead::where('phone', '9876543210')->first()->notes()->first()->body;
    expect($note)->toContain('GBP profile: NOT PROVIDED');
});

it('creates a new lead when no existing open lead matches the phone, capturing the website URL', function () {
    visibilityAuditWebhook(visibilityAuditPayload([
        'amount' => 24000,
        'notes' => ['Website URL' => 'https://shahtraders.example.com'],
    ]))->assertOk();

    $lead = Lead::where('phone', '+919876543210')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->email)->toBe('priya@shah.test')
        ->and($lead->source)->toBe(LeadSource::Other)
        ->and($lead->utm_source)->toBe('visibility-audit-offer')
        ->and($lead->notes()->first()->body)->toContain('Website Audit')
        ->and($lead->notes()->first()->body)->toContain('https://shahtraders.example.com');

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->first();
    expect($purchase->website_url)->toBe('https://shahtraders.example.com');
});

it('resolves the "both" tier, captures both URLs, and does not guess a single service', function () {
    Service::factory()->create(['name' => 'GMB']);
    Service::factory()->create(['name' => 'Website Design & Development']);

    visibilityAuditWebhook(visibilityAuditPayload([
        'amount' => 36000,
        'notes' => [
            'Google Business Profile Link' => 'https://maps.google.com/?cid=123',
            'Website URL' => 'https://shahtraders.example.com',
        ],
    ]))->assertOk();

    $lead = Lead::where('phone', '+919876543210')->first();
    expect($lead->service_id)->toBeNull()
        ->and($lead->notes()->first()->body)->toContain('GBP + Website Audit')
        ->and($lead->notes()->first()->body)->toContain('https://maps.google.com/?cid=123')
        ->and($lead->notes()->first()->body)->toContain('https://shahtraders.example.com');

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va_test1')->first();
    expect($purchase->gbp_url)->toBe('https://maps.google.com/?cid=123')
        ->and($purchase->website_url)->toBe('https://shahtraders.example.com');
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

it('re-scores the matched existing lead once a purchase is recorded', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $lead = Lead::factory()->create(['phone' => '9876543210', 'status' => LeadStatus::New]);

    // Fake only ScoreLead -- RecordVisibilityAuditPurchase itself must still
    // run for real (the sync queue connection would otherwise never execute
    // it, and there'd be nothing to assert a re-score against).
    Queue::fake([ScoreLead::class]);
    visibilityAuditWebhook(visibilityAuditPayload())->assertOk();

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});

it('re-scores a newly created lead once a purchase is recorded for it', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);

    // Fake only ScoreLead -- RecordVisibilityAuditPurchase itself must still
    // run for real (the sync queue connection would otherwise never execute
    // it, and there'd be nothing to assert a re-score against).
    Queue::fake([ScoreLead::class]);
    visibilityAuditWebhook(visibilityAuditPayload())->assertOk();

    $lead = Lead::where('phone', '+919876543210')->first();
    // Lead::create() fires an initial score dispatch via LeadObserver before
    // the payment note exists -- the job's own re-score after attaching the
    // note is the second push, and is the one that actually reflects payment.
    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});

it('does not re-score when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Lead::factory()->create(['phone' => '9876543210', 'status' => LeadStatus::New]);

    // Fake only ScoreLead -- RecordVisibilityAuditPurchase itself must still
    // run for real (the sync queue connection would otherwise never execute
    // it, and there'd be nothing to assert a re-score against).
    Queue::fake([ScoreLead::class]);
    visibilityAuditWebhook(visibilityAuditPayload())->assertOk();

    Queue::assertNothingPushed();
});
