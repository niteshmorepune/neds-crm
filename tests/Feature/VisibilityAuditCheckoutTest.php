<?php

use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'test-key-secret',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────
// order() — price is always ₹120 server-side, gbp_url is required
// ──────────────────────────────────────────────────────────────────────────

it('creates a Razorpay order for the fixed ₹120 GBP price, ignoring any client-supplied amount', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_va1', 'amount' => 12000, 'currency' => 'INR'])]);

    $this->postJson(route('offers.visibility-audit.order'), ['gbp_url' => 'https://g.co/kgs/abc123', 'amount' => 1])
        ->assertOk()
        ->assertJson(['order_id' => 'order_va1', 'amount' => 12000, 'key_id' => 'rzp_test_key']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['amount'] === 12000
        && $request['notes']['tier'] === VisibilityAuditTier::Gbp->value
        && $request['notes']['gbp_url'] === 'https://g.co/kgs/abc123');
});

it('requires gbp_url to create an order', function () {
    Http::fake();

    $this->postJson(route('offers.visibility-audit.order'), [])->assertStatus(422);
});

it('returns 503 from order() when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);

    $this->postJson(route('offers.visibility-audit.order'), ['gbp_url' => 'https://g.co/kgs/abc'])->assertStatus(503);
});

it('logs a PaymentViewed funnel event and attributes it to a lead passed via ?lead=', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_va2', 'amount' => 12000, 'currency' => 'INR'])]);
    $lead = Lead::factory()->create(['name' => 'Ramesh Traders', 'phone' => '9876543210']);

    $this->postJson(route('offers.visibility-audit.order').'?lead='.$lead->id, ['gbp_url' => 'https://g.co/kgs/abc'])
        ->assertOk()
        ->assertJson(['contact_name' => 'Ramesh Traders', 'contact_phone' => '9876543210']);

    $event = VisibilityAuditFunnelEvent::first();
    expect($event->lead_id)->toBe($lead->id);
    expect($event->tier)->toBe(VisibilityAuditTier::Gbp);
});

// ──────────────────────────────────────────────────────────────────────────
// verify() — signature + authoritative re-fetched amount/tier, never trust the client
// ──────────────────────────────────────────────────────────────────────────

it('verifies a correctly signed payment and records the purchase, matching the lead by phone', function () {
    $lead = Lead::factory()->create(['phone' => '9123456789']);

    Http::fake(['api.razorpay.com/v1/orders/order_va3' => Http::response([
        'id' => 'order_va3',
        'amount' => 12000,
        'notes' => ['tier' => 'gbp', 'gbp_url' => 'https://g.co/kgs/xyz', 'lead_id' => (string) $lead->id],
    ])]);
    $signature = hash_hmac('sha256', 'order_va3|pay_va3', 'test-key-secret');

    $this->postJson(route('offers.visibility-audit.verify'), [
        'razorpay_order_id' => 'order_va3',
        'razorpay_payment_id' => 'pay_va3',
        'razorpay_signature' => $signature,
    ])->assertOk()->assertJson(['status' => 'ok']);

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va3')->first();
    expect($purchase)->not->toBeNull();
    expect($purchase->tier)->toBe(VisibilityAuditTier::Gbp);
    expect($purchase->gbp_url)->toBe('https://g.co/kgs/xyz');
    expect($purchase->lead_id)->toBe($lead->id);
    expect($lead->fresh()->gbp_url)->toBe('https://g.co/kgs/xyz');
});

it('overwrites a stale existing gbp_url on the matched lead with the one captured at checkout', function () {
    $lead = Lead::factory()->create(['phone' => '9123456790', 'gbp_url' => 'https://g.co/kgs/old']);

    Http::fake(['api.razorpay.com/v1/orders/order_va9' => Http::response([
        'id' => 'order_va9',
        'amount' => 12000,
        'notes' => ['tier' => 'gbp', 'gbp_url' => 'https://g.co/kgs/corrected', 'lead_id' => (string) $lead->id],
    ])]);
    $signature = hash_hmac('sha256', 'order_va9|pay_va9', 'test-key-secret');

    $this->postJson(route('offers.visibility-audit.verify'), [
        'razorpay_order_id' => 'order_va9',
        'razorpay_payment_id' => 'pay_va9',
        'razorpay_signature' => $signature,
    ])->assertOk();

    expect($lead->fresh()->gbp_url)->toBe('https://g.co/kgs/corrected');
});

it('rejects verify with a bad signature', function () {
    $this->postJson(route('offers.visibility-audit.verify'), [
        'razorpay_order_id' => 'order_va4',
        'razorpay_payment_id' => 'pay_va4',
        'razorpay_signature' => 'not-the-real-signature',
    ])->assertStatus(422);

    expect(VisibilityAuditPurchase::count())->toBe(0);
});

it('rejects verify when the re-fetched order amount does not match ₹120', function () {
    Http::fake(['api.razorpay.com/v1/orders/order_va5' => Http::response([
        'id' => 'order_va5',
        'amount' => 100,
        'notes' => ['tier' => 'gbp'],
    ])]);
    $signature = hash_hmac('sha256', 'order_va5|pay_va5', 'test-key-secret');

    $this->postJson(route('offers.visibility-audit.verify'), [
        'razorpay_order_id' => 'order_va5',
        'razorpay_payment_id' => 'pay_va5',
        'razorpay_signature' => $signature,
    ])->assertStatus(422);

    expect(VisibilityAuditPurchase::count())->toBe(0);
});

it('rejects verify when the re-fetched order notes carry a different tier', function () {
    // Simulates a forged/tampered order id belonging to some other flow —
    // must never be accepted just because the amount happens to match.
    Http::fake(['api.razorpay.com/v1/orders/order_va6' => Http::response([
        'id' => 'order_va6',
        'amount' => 12000,
        'notes' => ['tier' => 'website'],
    ])]);
    $signature = hash_hmac('sha256', 'order_va6|pay_va6', 'test-key-secret');

    $this->postJson(route('offers.visibility-audit.verify'), [
        'razorpay_order_id' => 'order_va6',
        'razorpay_payment_id' => 'pay_va6',
        'razorpay_signature' => $signature,
    ])->assertStatus(422);

    expect(VisibilityAuditPurchase::count())->toBe(0);
});

it('is idempotent — a second verify() call for the same payment id does not double-record', function () {
    Http::fake(['api.razorpay.com/v1/orders/order_va7' => Http::response([
        'id' => 'order_va7',
        'amount' => 12000,
        'notes' => ['tier' => 'gbp', 'gbp_url' => 'https://g.co/kgs/dup'],
    ])]);
    $signature = hash_hmac('sha256', 'order_va7|pay_va7', 'test-key-secret');
    $payload = [
        'razorpay_order_id' => 'order_va7',
        'razorpay_payment_id' => 'pay_va7',
        'razorpay_signature' => $signature,
    ];

    $this->postJson(route('offers.visibility-audit.verify'), $payload)->assertOk();
    $this->postJson(route('offers.visibility-audit.verify'), $payload)->assertOk();

    expect(VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va7')->count())->toBe(1);
});

it('falls back to fetching the Razorpay payment entity for contact details when no lead was attributed', function () {
    Http::fake([
        'api.razorpay.com/v1/orders/order_va8' => Http::response([
            'id' => 'order_va8',
            'amount' => 12000,
            'notes' => ['tier' => 'gbp', 'gbp_url' => 'https://g.co/kgs/anon'],
        ]),
        'api.razorpay.com/v1/payments/pay_va8' => Http::response([
            'id' => 'pay_va8',
            'contact' => '9988776655',
            'email' => 'anon@example.com',
        ]),
    ]);
    $signature = hash_hmac('sha256', 'order_va8|pay_va8', 'test-key-secret');

    $this->postJson(route('offers.visibility-audit.verify'), [
        'razorpay_order_id' => 'order_va8',
        'razorpay_payment_id' => 'pay_va8',
        'razorpay_signature' => $signature,
    ])->assertOk();

    $purchase = VisibilityAuditPurchase::where('razorpay_payment_id', 'pay_va8')->first();
    expect($purchase->payer_phone)->toBe('9988776655');
    expect($purchase->payer_email)->toBe('anon@example.com');
});
