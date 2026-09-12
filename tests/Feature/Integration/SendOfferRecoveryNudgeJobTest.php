<?php

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferPurchaseStatus;
use App\Jobs\SendOfferRecoveryNudgeJob;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.offer_recommendation_recovery_template_name' => 'offer_recommendation_recovery',
        'services.wadesk.offer_recovery_template_name' => 'offer_recovery',
    ]);
    Queue::fake();
});

function leadWithToken(array $overrides = []): Lead
{
    return Lead::factory()->create(array_merge([
        'phone' => '+91 98765 43210',
        'recommendation_token' => (string) Str::uuid(),
    ], $overrides));
}

it('sends the softer recommendation-stage template and marks the event nudged', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken(['name' => 'Priya Shah']);
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::RecommendationCreated))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request['templateName'] === 'offer_recommendation_recovery'
            && $request['variables'] === ['Priya Shah']
            && $request['buttonUrlParam'] === $lead->recommendation_token;
    });

    expect($event->fresh()->nudged_at)->not->toBeNull();
});

it('sends the hotter offer-stage template', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertSent(fn ($request) => $request['templateName'] === 'offer_recovery');
});

it('never sends twice for the same event once already nudged', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create([
        'event_type' => OfferFunnelEventType::OfferViewed,
        'lead_id' => $lead->id,
        'nudged_at' => now(),
    ]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertNothingSent();
});

it('skips sending when the lead paid after the job was queued but before it ran', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);
    OfferPurchase::create([
        'offer_key' => 'lead_generation_audit',
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Paid,
        'razorpay_order_id' => 'order_offer_recovery_race1',
        'lead_id' => $lead->id,
    ]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertNothingSent();
    expect($event->fresh()->nudged_at)->toBeNull();
});

it('skips sending when staff already replied over WhatsApp since the event', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Kiran Katte]\nYes we'll follow up"]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertNothingSent();
    expect($event->fresh()->nudged_at)->toBeNull();
});

it('no-ops when the template for that stage is not configured', function () {
    config(['services.wadesk.offer_recovery_template_name' => null]);
    Http::fake();

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertNothingSent();
    expect($event->fresh()->nudged_at)->toBeNull();
});

it('no-ops when the lead has no recommendation token', function () {
    Http::fake();

    $lead = Lead::factory()->create(['phone' => '+91 98765 43210', 'recommendation_token' => null]);
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertNothingSent();
});
