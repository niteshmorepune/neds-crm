<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use App\Enums\VisibilityAuditTier;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\OfferPurchase;
use App\Models\VisibilityAuditPurchase;
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

// ──────────────────────────────────────────────────────────────────────────
// Dashboard aggregation — funnelSummary/byOfferBreakdown/trend/leadsForStage/
// byGoalBudgetBreakdown, added for the team-wide Offer Funnel dashboard.
// ──────────────────────────────────────────────────────────────────────────

it('funnelSummary() counts each stage across all 3 non-GBP offers when $offer is null', function () {
    // Went all the way: recommended, notified, viewed, reached offer, paid.
    $paid = Lead::factory()->create([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_generated_at' => now(),
        'recommendation_notified_at' => now(),
        'recommendation_viewed_at' => now(),
        'offer_clicked_at' => now(),
    ]);
    OfferPurchase::create([
        'offer_key' => 'lead_generation_audit',
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Paid,
        'razorpay_order_id' => 'order_summary_paid1',
        'lead_id' => $paid->id,
    ]);

    // Recommended + notified only, different offer — still counted since $offer is null.
    Lead::factory()->create([
        'recommendation_offer_key' => 'growth_strategy',
        'recommendation_generated_at' => now(),
        'recommendation_notified_at' => now(),
    ]);

    // Not eligible at all — no recommendation ever generated.
    Lead::factory()->create(['recommendation_offer_key' => null, 'recommendation_generated_at' => null]);

    // GbpAudit-recommended — must never count toward the non-GBP summary.
    Lead::factory()->create(['recommendation_offer_key' => 'gbp_audit', 'recommendation_generated_at' => now()]);

    $summary = app(OfferFunnelMetrics::class)->funnelSummary(null);

    expect($summary['recommended'])->toBe(2)
        ->and($summary['notified'])->toBe(2)
        ->and($summary['viewed'])->toBe(1)
        ->and($summary['reached_offer'])->toBe(1)
        ->and($summary['paid'])->toBe(1);
});

it('funnelSummary() scopes to one specific offer when given', function () {
    Lead::factory()->create(['recommendation_offer_key' => 'lead_generation_audit', 'recommendation_generated_at' => now()]);
    Lead::factory()->create(['recommendation_offer_key' => 'growth_strategy', 'recommendation_generated_at' => now()]);

    $summary = app(OfferFunnelMetrics::class)->funnelSummary(OfferKey::GrowthStrategy);

    expect($summary['recommended'])->toBe(1);
});

it('funnelSummary() returns null percentages instead of a misleading 0% when a prior stage is empty', function () {
    $summary = app(OfferFunnelMetrics::class)->funnelSummary(null);

    expect($summary['recommended'])->toBe(0)
        ->and($summary['notified_pct'])->toBeNull()
        ->and($summary['overall_pct'])->toBeNull();
});

it('byOfferBreakdown() returns one row per non-GBP offer, correctly scoped', function () {
    Lead::factory()->create(['recommendation_offer_key' => 'lead_generation_audit', 'recommendation_generated_at' => now()]);
    Lead::factory()->create(['recommendation_offer_key' => 'website_growth_audit', 'recommendation_generated_at' => now()]);
    Lead::factory()->create(['recommendation_offer_key' => 'website_growth_audit', 'recommendation_generated_at' => now()]);

    $byOffer = app(OfferFunnelMetrics::class)->byOfferBreakdown();

    expect($byOffer['lead_generation_audit']['recommended'])->toBe(1)
        ->and($byOffer['website_growth_audit']['recommended'])->toBe(2)
        ->and($byOffer['growth_strategy']['recommended'])->toBe(0)
        ->and($byOffer)->not->toHaveKey('gbp_audit');
});

it('trend() buckets recommended/paid by Asia/Kolkata calendar date', function () {
    $tz = 'Asia/Kolkata';
    $today = now($tz)->startOfDay();

    $lead = Lead::factory()->create([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_generated_at' => $today->copy()->addHours(10)->utc(),
    ]);
    OfferPurchase::create([
        'offer_key' => 'lead_generation_audit',
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Paid,
        'razorpay_order_id' => 'order_trend_paid1',
        'lead_id' => $lead->id,
        'paid_at' => $today->copy()->addHours(11)->utc(),
    ]);

    $trend = app(OfferFunnelMetrics::class)->trend(
        $today->copy()->subDay()->utc(),
        $today->copy()->addDay()->endOfDay()->utc()
    );

    $todayRow = collect($trend)->firstWhere('label', $today->format('d M'));

    expect($todayRow['recommended'])->toBe(1)
        ->and($todayRow['paid'])->toBe(1);
});

it('leadsForStage() returns the right leads for each stage and throws on an unknown stage', function () {
    $lead = Lead::factory()->create([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_generated_at' => now(),
        'recommendation_viewed_at' => now(),
    ]);

    $metrics = app(OfferFunnelMetrics::class);

    expect($metrics->leadsForStage(OfferKey::LeadGenerationAudit, 'viewed')->pluck('id'))->toContain($lead->id);
    expect($metrics->leadsForStage(OfferKey::LeadGenerationAudit, 'reached_offer')->pluck('id'))->not->toContain($lead->id);
    expect(fn () => $metrics->leadsForStage(null, 'not-a-real-stage'))->toThrow(InvalidArgumentException::class);
});

it('byGoalBudgetBreakdown() counts recommended + paid per goal x budget cell, across every offer', function () {
    // Recommended into a non-GBP offer, paid via OfferPurchase.
    $nonGbpPaid = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_generated_at' => now(),
        'recommendation_offer_key' => 'lead_generation_audit',
    ]);
    OfferPurchase::create([
        'offer_key' => 'lead_generation_audit',
        'price_paise' => 29900,
        'status' => OfferPurchaseStatus::Paid,
        'razorpay_order_id' => 'order_goalbudget_paid1',
        'lead_id' => $nonGbpPaid->id,
    ]);

    // Recommended into GBP, paid via the SEPARATE VisibilityAuditPurchase table
    // — must still show up here, since this breakdown is offer-agnostic.
    $gbpPaid = Lead::factory()->create([
        'goal' => LeadGoal::RankHigher,
        'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_generated_at' => now(),
        'recommendation_offer_key' => 'gbp_audit',
    ]);
    VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_goalbudget_gbp1',
        'lead_id' => $gbpPaid->id,
    ]);

    // Recommended but not paid.
    Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_generated_at' => now(),
        'recommendation_offer_key' => 'website_growth_audit',
    ]);

    // No goal/budget captured at all — must never appear in any cell.
    Lead::factory()->create(['goal' => null, 'budget_range' => null, 'recommendation_generated_at' => null]);

    $cells = app(OfferFunnelMetrics::class)->byGoalBudgetBreakdown();

    expect($cells['generate_leads']['under_3000'])->toBe(['recommended' => 2, 'paid' => 1])
        ->and($cells['rank_higher']['under_3000'])->toBe(['recommended' => 1, 'paid' => 1])
        ->and($cells['grow_business']['under_3000'])->toBe(['recommended' => 0, 'paid' => 0]);
});
