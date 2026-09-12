<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Jobs\SendLeadWelcomeMessageJob;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Jobs\SendVisibilityAuditFirstInviteEmailJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Facades\Queue;

/**
 * LeadObserver::routeMetaLeadFirstTouch() — the unified funnel's routing
 * decision, replacing the old sendVisibilityAuditInviteIfEligible()/
 * sendWelcomeMessageIfEligible() split that decided purely from
 * service_id === gmbServiceId(). See the 2026-09-12 "unified funnel"
 * decisions log entry. VisibilityAuditFirstInviteTest.php already covers
 * every pre-existing service-tag-fallback scenario in detail (no goal/budget
 * captured) — this file covers the NEW matrix-decides behavior and the
 * highest-value regression case (a GMB-tagged lead whose real answers point
 * somewhere else).
 */
beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
    ]);
    $this->gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    Queue::fake();
});

it('routes a Meta lead to SendOfferRecommendationReadyJob when its own goal/budget resolve to a non-GBP offer, regardless of service tag', function () {
    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);

    Queue::assertPushed(SendOfferRecommendationReadyJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('routes a non-GMB-tagged Meta lead to the existing VA invite when its own goal/budget resolve to GbpAudit', function () {
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $seo->id,
        'goal' => LeadGoal::RankHigher,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertPushed(SendVisibilityAuditFirstInviteEmailJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('falls back to the VA invite for a GMB-tagged lead with no goal/budget captured yet', function () {
    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $this->gmb->id,
        'goal' => null,
        'budget_range' => null,
    ]);

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('falls back to the generic welcome message for a non-GMB Meta lead with no goal/budget captured yet', function () {
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => $seo->id,
        'goal' => null,
        'budget_range' => null,
    ]);

    Queue::assertPushed(SendLeadWelcomeMessageJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('falls back to the generic welcome message for a Meta lead with no service tagged and no goal/budget', function () {
    $lead = Lead::factory()->create([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'service_id' => null,
        'goal' => null,
        'budget_range' => null,
    ]);

    Queue::assertPushed(SendLeadWelcomeMessageJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

it('dispatches nothing for a lead with no meta_leadgen_id, even with goal/budget captured', function () {
    Lead::factory()->create([
        'source' => LeadSource::Website,
        'meta_leadgen_id' => null,
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
    Queue::assertNotPushed(SendLeadWelcomeMessageJob::class);
});

it('routes correctly via the updated() backfill path when meta_leadgen_id/service_id land after creation', function () {
    // Same real race condition as VisibilityAuditFirstInviteTest's own
    // backfill test — the Lead is created first via WhatsApp with neither
    // field set, then ImportMetaLead::attachToExistingLead() backfills them
    // a moment later via a plain update(), never re-triggering created().
    $lead = Lead::factory()->create([
        'source' => LeadSource::Whatsapp,
        'meta_leadgen_id' => null,
        'service_id' => null,
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);

    $lead->update(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $this->gmb->id]);

    Queue::assertPushed(SendOfferRecommendationReadyJob::class, fn ($job) => $job->leadId === $lead->id);
});
