<?php

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferPurchaseStatus;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use App\Services\OfferFunnelMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function backdateOfferEvent(OfferFunnelEvent $event, Carbon $createdAt): OfferFunnelEvent
{
    $event->created_at = $createdAt;
    $event->save();

    return $event;
}

function nonGbpLead(array $overrides = []): Lead
{
    return Lead::factory()->create(array_merge([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => (string) Str::uuid(),
        'recommendation_generated_at' => now(),
    ], $overrides));
}

it('surfaces a lead stuck at the recommendation stage once past the wait threshold', function () {
    $recent = nonGbpLead();
    backdateOfferEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $recent->id]), now()->subMinutes(10));

    $old = nonGbpLead();
    backdateOfferEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $old->id]), now()->subHours(5));

    $pending = app(OfferFunnelMetrics::class)->pendingRecommendationNudges(now()->subHours(4))->pluck('id');

    expect($pending)->not->toContain($recent->id);
    expect($pending)->toContain($old->id);
});

it('excludes a lead from recommendation nudges once they reach the offer page', function () {
    $lead = nonGbpLead(['offer_viewed_at' => now()]);
    backdateOfferEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]), now()->subHours(5));

    $pending = app(OfferFunnelMetrics::class)->pendingRecommendationNudges(now()->subHours(4))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('surfaces a lead stuck at the offer stage (viewed or clicked) once past the wait threshold', function () {
    $lead = nonGbpLead(['offer_viewed_at' => now()->subHours(3)]);
    backdateOfferEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]), now()->subHours(3));

    $pending = app(OfferFunnelMetrics::class)->pendingOfferNudges(now()->subHours(2))->pluck('id');

    expect($pending)->toContain($lead->id);
});

it('excludes an already-nudged lead from pending, even past the threshold', function () {
    $lead = nonGbpLead(['offer_viewed_at' => now()->subHours(3)]);
    $event = OfferFunnelEvent::create([
        'event_type' => OfferFunnelEventType::OfferViewed,
        'lead_id' => $lead->id,
        'nudged_at' => now()->subHours(1),
    ]);
    backdateOfferEvent($event, now()->subHours(3));

    $pending = app(OfferFunnelMetrics::class)->pendingOfferNudges(now()->subHours(2))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('excludes a lead who already paid from pending offer nudges', function () {
    $lead = nonGbpLead(['offer_viewed_at' => now()->subHours(3)]);
    backdateOfferEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]), now()->subHours(3));
    OfferPurchase::create([
        'offer_key' => 'lead_generation_audit',
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Paid,
        'razorpay_order_id' => 'order_metrics_paid1',
        'lead_id' => $lead->id,
    ]);

    $pending = app(OfferFunnelMetrics::class)->pendingOfferNudges(now()->subHours(2))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('excludes a lead staff already replied to over WhatsApp since the event', function () {
    $lead = nonGbpLead(['offer_viewed_at' => now()->subHours(3)]);
    $event = OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]);
    backdateOfferEvent($event, now()->subHours(3));
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nWe'll follow up shortly"]);

    $pending = app(OfferFunnelMetrics::class)->pendingOfferNudges(now()->subHours(2))->pluck('id');

    expect($pending)->not->toContain($lead->id);
});

it('excludes a GbpAudit-recommended lead entirely, from both stages', function () {
    $lead = Lead::factory()->create([
        'recommendation_offer_key' => 'gbp_audit',
        'recommendation_generated_at' => now()->subHours(5),
    ]);
    backdateOfferEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]), now()->subHours(5));

    expect(app(OfferFunnelMetrics::class)->pendingRecommendationNudges(now()->subHours(4))->pluck('id'))->not->toContain($lead->id);
});
