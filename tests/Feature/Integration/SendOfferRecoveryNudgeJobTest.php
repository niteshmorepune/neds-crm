<?php

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferPurchaseStatus;
use App\Jobs\SendOfferRecoveryNudgeJob;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key_messaging' => 'wadesk-secret',
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

    // The recommendation-ready message went out — this nudge follows it up.
    $lead = leadWithToken(['name' => 'Priya Shah', 'recommendation_notified_at' => now()]);
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::RecommendationCreated))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request['templateName'] === 'offer_recommendation_recovery'
            && $request['variables'] === ['Priya Shah', 'Priya Shah']
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

/**
 * Real incident, lead #322 (2026-09-13): staff had been actively working
 * this lead entirely by phone (a Connected call telling the client "your
 * proposal is ready"), no WhatsApp-tagged note in between — the guard at
 * the time only checked hasStaffWhatsappReplySince(), so this automated
 * nudge fired an unrelated ₹299 offer on top of a live human conversation.
 */
it('skips sending when staff already engaged the lead by phone since the event', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);
    CallLog::factory()->create([
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'called_at' => now(),
    ]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::OfferViewed))->handle();

    Http::assertNothingSent();
    expect($event->fresh()->nudged_at)->toBeNull();
});

it('skips sending when staff already left a plain note on the lead since the event', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithToken();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);
    $lead->notes()->create([
        'user_id' => User::factory()->create()->id,
        'body' => 'I informed the client that the proposal is ready and scheduled a call at 2 PM.',
    ]);

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

/*
 * Real incident, lead #473 (2026-09-23): staff replied on WhatsApp and noted
 * an office visit at 11:56–11:57, the recommendation was generated at 11:58,
 * and the recovery nudge still fired at 16:01 — the engagement check only
 * looked for contact AFTER the funnel event.
 */
it('skips sending when staff engaged the lead shortly before the funnel event', function () {
    Http::fake();
    $this->travelTo(now()->subHours(5));
    $lead = leadWithToken(['recommendation_notified_at' => now()]);
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Admin]\nOur office location is …"]);
    $lead->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'He is going to visit the office tomorrow']);
    $this->travel(2)->minutes();
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]);
    $this->travelBack();

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::RecommendationCreated))->handle();

    Http::assertNothingSent();
    expect($event->fresh()->nudged_at)->toBeNull();
});

it('never asks "did you see the recommendation" when the recommendation message was never sent', function () {
    Http::fake();
    $lead = leadWithToken(['recommendation_notified_at' => null]);
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]);

    (new SendOfferRecoveryNudgeJob($lead->id, $event->id, OfferFunnelEventType::RecommendationCreated))->handle();

    Http::assertNothingSent();
});
