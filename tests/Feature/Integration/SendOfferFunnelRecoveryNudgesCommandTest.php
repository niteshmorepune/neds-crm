<?php

use App\Enums\OfferFunnelEventType;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Jobs\SendOfferRecoveryNudgeJob;
use App\Jobs\SendVisibilityAuditRecoveryNudgeEmailJob;
use App\Jobs\SendVisibilityAuditRecoveryNudgeJob;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use App\Models\VisibilityAuditFunnelEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The unified funnel's one recovery-nudge cron — replaces the old, GBP-only
 * app:send-visibility-audit-recovery-nudges. VisibilityAuditRecoveryNudgeTest
 * already covers the GBP path's own job/metrics behavior in full detail
 * (unchanged by this milestone); this file covers the command's own
 * responsibility of combining both pipelines into one dispatch pass.
 */
function backdateFunnelEvent(mixed $event, Carbon $createdAt): mixed
{
    $event->created_at = $createdAt;
    $event->save();

    return $event;
}

beforeEach(function () {
    Queue::fake();
});

it('dispatches the existing VA jobs for a GbpAudit-recommended (GMB) lead stuck at checkout', function () {
    $lead = Lead::factory()->create();
    backdateFunnelEvent(VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $lead->id]), now()->subHours(3));

    Artisan::call('app:send-offer-funnel-recovery-nudges');

    Queue::assertPushed(SendVisibilityAuditRecoveryNudgeJob::class, fn ($job) => $job->leadId === $lead->id && $job->stage === VisibilityAuditFunnelEventType::PaymentViewed);
    Queue::assertPushed(SendVisibilityAuditRecoveryNudgeEmailJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches the new offer job for a non-GBP-recommended lead stuck at the offer stage', function () {
    $lead = Lead::factory()->create([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => (string) Str::uuid(),
        'offer_viewed_at' => now()->subHours(3),
    ]);
    backdateFunnelEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $lead->id]), now()->subHours(3));

    Artisan::call('app:send-offer-funnel-recovery-nudges');

    Queue::assertPushed(SendOfferRecoveryNudgeJob::class, fn ($job) => $job->leadId === $lead->id && $job->stage === OfferFunnelEventType::OfferViewed);
});

it('dispatches the new offer job for a non-GBP-recommended lead stuck at the recommendation stage', function () {
    $lead = Lead::factory()->create([
        'recommendation_offer_key' => 'growth_strategy',
        'recommendation_token' => (string) Str::uuid(),
        'recommendation_generated_at' => now()->subHours(5),
    ]);
    backdateFunnelEvent(OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::RecommendationCreated, 'lead_id' => $lead->id]), now()->subHours(5));

    Artisan::call('app:send-offer-funnel-recovery-nudges');

    Queue::assertPushed(SendOfferRecoveryNudgeJob::class, fn ($job) => $job->leadId === $lead->id && $job->stage === OfferFunnelEventType::RecommendationCreated);
});

it('does not dispatch for a lead still inside the wait threshold on either pipeline', function () {
    $gbpLead = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::PaymentViewed, 'lead_id' => $gbpLead->id]);

    $offerLead = Lead::factory()->create([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => (string) Str::uuid(),
        'offer_viewed_at' => now(),
    ]);
    OfferFunnelEvent::create(['event_type' => OfferFunnelEventType::OfferViewed, 'lead_id' => $offerLead->id]);

    Artisan::call('app:send-offer-funnel-recovery-nudges');

    Queue::assertNotPushed(SendVisibilityAuditRecoveryNudgeJob::class);
    Queue::assertNotPushed(SendOfferRecoveryNudgeJob::class);
});
