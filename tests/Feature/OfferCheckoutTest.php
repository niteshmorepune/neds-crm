<?php

use App\Enums\LeadStatus;
use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use App\Jobs\RecordOfferPurchase;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'test-key-secret',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────
// order() — price is always resolved server-side from OfferKey
// ──────────────────────────────────────────────────────────────────────────

it('creates a Razorpay order with the server-computed price for a valid offer, ignoring any client-supplied amount', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_lg1', 'amount' => 29900, 'currency' => 'INR'])]);

    $this->postJson(route('offers.checkout.order', OfferKey::LeadGenerationAudit->value), ['amount' => 1])
        ->assertOk()
        ->assertJson(['order_id' => 'order_lg1', 'amount' => 29900, 'key_id' => 'rzp_test_key']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['amount'] === 29900
        && $request['notes']['offer_key'] === 'lead_generation_audit');

    expect(OfferPurchase::where('razorpay_order_id', 'order_lg1')->first())
        ->offer_key->toBe(OfferKey::LeadGenerationAudit)
        ->price_paise->toBe(29900)
        ->status->toBe(OfferPurchaseStatus::Pending);
});

it('404s order() for the GBP offer — it keeps its own separate Payment Page checkout', function () {
    Http::fake();

    $this->postJson(route('offers.checkout.order', OfferKey::GbpAudit->value))->assertNotFound();
});

it('404s order() for an unknown offer key', function () {
    $this->postJson(route('offers.checkout.order', 'not-a-real-offer'))->assertNotFound();
});

it('returns 503 from order() when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);

    $this->postJson(route('offers.checkout.order', OfferKey::GrowthStrategy->value))->assertStatus(503);
});

// ──────────────────────────────────────────────────────────────────────────
// website_url — collected only for WebsiteGrowthAudit, reflected onto the Lead
// ──────────────────────────────────────────────────────────────────────────

it('requires website_url to create an order for the Website Growth Audit offer', function () {
    Http::fake();

    $this->postJson(route('offers.checkout.order', OfferKey::WebsiteGrowthAudit->value), [])
        ->assertStatus(422);
});

it('stores the submitted website_url on the order and the purchase for Website Growth Audit', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_wg1', 'amount' => 49900, 'currency' => 'INR'])]);

    $this->postJson(route('offers.checkout.order', OfferKey::WebsiteGrowthAudit->value), ['website_url' => 'https://mysite.example.com'])
        ->assertOk();

    Http::assertSent(fn ($request) => $request['notes']['website_url'] === 'https://mysite.example.com');
    expect(OfferPurchase::where('razorpay_order_id', 'order_wg1')->first()->website_url)->toBe('https://mysite.example.com');
});

it('does not require or store website_url for the other 2 non-GBP offers', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_lg9', 'amount' => 29900, 'currency' => 'INR'])]);

    $this->postJson(route('offers.checkout.order', OfferKey::LeadGenerationAudit->value), [])->assertOk();

    Http::assertSent(fn ($request) => ! array_key_exists('website_url', $request['notes']));
    expect(OfferPurchase::where('razorpay_order_id', 'order_lg9')->first()->website_url)->toBeNull();
});

it('reflects a captured website_url onto the matched lead once the payment is recorded', function () {
    $lead = Lead::factory()->create(['phone' => '9812300001', 'website_url' => null]);

    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::WebsiteGrowthAudit->value,
        'price_paise' => 49900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_wg2',
        'payer_phone' => '9812300001',
        'website_url' => 'https://freshly-typed.example.com',
    ]);

    (new RecordOfferPurchase($purchase->id, 'pay_wg2'))->handle();

    expect($lead->fresh()->website_url)->toBe('https://freshly-typed.example.com');
});

it('overwrites a stale existing website_url on the lead with the one captured at checkout', function () {
    $lead = Lead::factory()->create(['phone' => '9812300002', 'website_url' => 'https://old-site.example.com']);

    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::WebsiteGrowthAudit->value,
        'price_paise' => 49900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_wg3',
        'lead_id' => $lead->id,
        'payer_phone' => '9812300002',
        'website_url' => 'https://corrected-site.example.com',
    ]);

    (new RecordOfferPurchase($purchase->id, 'pay_wg3'))->handle();

    expect($lead->fresh()->website_url)->toBe('https://corrected-site.example.com');
});

it('attributes the order to a lead passed via ?lead= and logs CTA-clicked/payment-started events', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_lg2', 'amount' => 29900, 'currency' => 'INR'])]);
    $lead = Lead::factory()->create(['name' => 'Ramesh Traders', 'phone' => '9876543210']);

    $this->postJson(route('offers.checkout.order', OfferKey::LeadGenerationAudit->value).'?lead='.$lead->id)
        ->assertOk()
        ->assertJson(['contact_name' => 'Ramesh Traders']);

    expect(OfferPurchase::where('razorpay_order_id', 'order_lg2')->first()->lead_id)->toBe($lead->id);
    expect($lead->fresh()->offer_clicked_at)->not->toBeNull();
    expect(OfferFunnelEvent::where('lead_id', $lead->id)->where('event_type', 'offer_cta_clicked')->exists())->toBeTrue();
    expect(OfferFunnelEvent::where('lead_id', $lead->id)->where('event_type', 'payment_started')->exists())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────
// verify() — signature + authoritative re-fetched amount/offer, never trust the client
// ──────────────────────────────────────────────────────────────────────────

it('verifies a correctly signed payment and marks the purchase paid', function () {
    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::GrowthStrategy->value,
        'price_paise' => 99900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_gs1',
    ]);

    Http::fake(['api.razorpay.com/v1/orders/order_gs1' => Http::response(['id' => 'order_gs1', 'amount' => 99900])]);
    $signature = hash_hmac('sha256', 'order_gs1|pay_gs1', 'test-key-secret');

    $this->postJson(route('offers.checkout.verify', OfferKey::GrowthStrategy->value), [
        'razorpay_order_id' => 'order_gs1',
        'razorpay_payment_id' => 'pay_gs1',
        'razorpay_signature' => $signature,
    ])->assertOk()->assertJson(['status' => 'ok']);

    expect($purchase->fresh())
        ->status->toBe(OfferPurchaseStatus::Paid)
        ->razorpay_payment_id->toBe('pay_gs1');
});

it('rejects verify with a bad signature and leaves the purchase pending', function () {
    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::GrowthStrategy->value,
        'price_paise' => 99900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_gs2',
    ]);

    $this->postJson(route('offers.checkout.verify', OfferKey::GrowthStrategy->value), [
        'razorpay_order_id' => 'order_gs2',
        'razorpay_payment_id' => 'pay_gs2',
        'razorpay_signature' => 'not-the-real-signature',
    ])->assertStatus(422);

    expect($purchase->fresh()->status)->toBe(OfferPurchaseStatus::Pending);
});

it('rejects verify when the re-fetched order amount does not match this offer\'s real price', function () {
    // Simulates a tampered/forged amount — the order the client claims to
    // have paid does not actually carry the price this offer is meant to
    // cost, so it must never be accepted no matter how the signature checks out.
    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::GrowthStrategy->value,
        'price_paise' => 99900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_gs3',
    ]);

    Http::fake(['api.razorpay.com/v1/orders/order_gs3' => Http::response(['id' => 'order_gs3', 'amount' => 100])]);
    $signature = hash_hmac('sha256', 'order_gs3|pay_gs3', 'test-key-secret');

    $this->postJson(route('offers.checkout.verify', OfferKey::GrowthStrategy->value), [
        'razorpay_order_id' => 'order_gs3',
        'razorpay_payment_id' => 'pay_gs3',
        'razorpay_signature' => $signature,
    ])->assertStatus(422);

    expect($purchase->fresh()->status)->toBe(OfferPurchaseStatus::Pending);
});

it('rejects verify when the order belongs to a different offer than the one in the URL', function () {
    OfferPurchase::create([
        'offer_key' => OfferKey::LeadGenerationAudit->value,
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_mismatch',
    ]);

    Http::fake(['api.razorpay.com/v1/orders/order_mismatch' => Http::response(['id' => 'order_mismatch', 'amount' => 29900])]);
    $signature = hash_hmac('sha256', 'order_mismatch|pay_x', 'test-key-secret');

    // Same order/signature, but verified against the WRONG offer's route.
    $this->postJson(route('offers.checkout.verify', OfferKey::GrowthStrategy->value), [
        'razorpay_order_id' => 'order_mismatch',
        'razorpay_payment_id' => 'pay_x',
        'razorpay_signature' => $signature,
    ])->assertStatus(422);
});

it('is idempotent — a second verify() call for an already-paid purchase does not error or double-process', function () {
    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::GrowthStrategy->value,
        'price_paise' => 99900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_gs4',
    ]);

    Http::fake(['api.razorpay.com/v1/orders/order_gs4' => Http::response(['id' => 'order_gs4', 'amount' => 99900])]);
    $signature = hash_hmac('sha256', 'order_gs4|pay_gs4', 'test-key-secret');
    $payload = [
        'razorpay_order_id' => 'order_gs4',
        'razorpay_payment_id' => 'pay_gs4',
        'razorpay_signature' => $signature,
    ];

    $this->postJson(route('offers.checkout.verify', OfferKey::GrowthStrategy->value), $payload)->assertOk();
    $this->postJson(route('offers.checkout.verify', OfferKey::GrowthStrategy->value), $payload)->assertOk();

    expect($purchase->fresh()->status)->toBe(OfferPurchaseStatus::Paid);
});

// ──────────────────────────────────────────────────────────────────────────
// RecordOfferPurchase — matches/creates a Lead, mirrors RecordVisibilityAuditPurchase
// ──────────────────────────────────────────────────────────────────────────

it('matches an existing open lead by phone when no lead was attributed at order() time', function () {
    $lead = Lead::factory()->create(['phone' => '9123456789', 'status' => LeadStatus::New]);

    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::LeadGenerationAudit->value,
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_match1',
        'payer_phone' => '9123456789',
    ]);

    (new RecordOfferPurchase($purchase->id, 'pay_match1'))->handle();

    expect($purchase->fresh())->status->toBe(OfferPurchaseStatus::Paid)->lead_id->toBe($lead->id);
    expect(OfferFunnelEvent::where('lead_id', $lead->id)->where('event_type', 'payment_succeeded')->exists())->toBeTrue();
});

it('creates a new lead when the payer phone matches no existing open lead', function () {
    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::LeadGenerationAudit->value,
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_match2',
        'payer_phone' => '9999999999',
        'payer_name' => 'New Customer',
    ]);

    (new RecordOfferPurchase($purchase->id, 'pay_match2'))->handle();

    $lead = Lead::where('phone', '9999999999')->first();
    expect($lead)->not->toBeNull();
    expect($purchase->fresh()->lead_id)->toBe($lead->id);
});

it('does not double-process a duplicate razorpay_payment_id (idempotency guard)', function () {
    $purchase = OfferPurchase::create([
        'offer_key' => OfferKey::LeadGenerationAudit->value,
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Pending,
        'razorpay_order_id' => 'order_dupe',
        'razorpay_payment_id' => 'pay_dupe',
    ]);
    $purchase->update(['status' => OfferPurchaseStatus::Paid, 'paid_at' => now()]);

    (new RecordOfferPurchase($purchase->id, 'pay_dupe'))->handle();

    expect(OfferFunnelEvent::where('event_type', 'payment_succeeded')->count())->toBe(0);
});
