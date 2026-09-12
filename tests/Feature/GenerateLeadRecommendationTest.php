<?php

use App\Actions\GenerateLeadRecommendation;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Jobs\SendVisibilityAuditFirstInviteEmailJob;
use App\Jobs\SendVisibilityAuditFirstInviteJob;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // LeadObserver dispatches several jobs on every eligible Lead::factory()
    // create below — fake the queue so these tests stay scoped to what
    // GenerateLeadRecommendation::handle() itself pushes when called
    // directly. Leads below are created WITHOUT goal/budget/meta_leadgen_id
    // set together (so the observer's own creation-time dispatch is always a
    // no-op — either goal/budget is missing, or meta_leadgen_id is), then
    // updated quietly before the action is invoked directly, so every
    // dispatch asserted below is genuinely the action's own, not a leftover
    // from Lead::factory()->create() itself.
    Queue::fake();
});

function metaLeadWithoutRecommendation(array $overrides = []): Lead
{
    $lead = Lead::factory()->create(['meta_leadgen_id' => null]);
    $lead->forceFill(array_merge(['meta_leadgen_id' => 'lg_'.uniqid()], $overrides))->saveQuietly();

    return $lead->fresh();
}

it('dispatches SendOfferRecommendationReadyJob for a Meta lead recommended into a non-GBP offer', function () {
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::GenerateLeads->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    Queue::assertPushed(SendOfferRecommendationReadyJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

it('dispatches the existing VA jobs, not SendOfferRecommendationReadyJob, when the matrix recommends GbpAudit', function () {
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::RankHigher->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertPushed(SendVisibilityAuditFirstInviteEmailJob::class, fn ($job) => $job->leadId === $lead->id);
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('never dispatches a first-touch message for a lead with no meta_leadgen_id, even with goal/budget captured', function () {
    // A rep manually filling in goal/budget on a Website/Referral/etc. lead
    // must never trigger an automated marketing WhatsApp send.
    $lead = Lead::factory()->create([
        'meta_leadgen_id' => null,
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);
    Queue::fake();

    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

it('does not dispatch again on a second call once the recommendation is unchanged', function () {
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::GenerateLeads->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);
    Queue::assertPushed(SendOfferRecommendationReadyJob::class, 1);

    Queue::fake();
    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('dispatches again when the recommendation later changes to a different offer', function () {
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::RankHigher->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);
    Queue::assertPushed(SendVisibilityAuditFirstInviteJob::class, 1);

    $lead->forceFill(['goal' => LeadGoal::GenerateLeads->value])->saveQuietly();
    Queue::fake();

    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    Queue::assertPushed(SendOfferRecommendationReadyJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('returns null and dispatches nothing when goal or budget is missing', function () {
    $lead = metaLeadWithoutRecommendation(['goal' => null, 'budget_range' => null]);

    $result = app(GenerateLeadRecommendation::class)->handle($lead);

    expect($result)->toBeNull();
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
    Queue::assertNotPushed(SendVisibilityAuditFirstInviteJob::class);
});

// ──────────────────────────────────────────────────────────────────────────
// service_id auto-derive from the resolved recommendation — 2026-09-12
// "service tag auto-derive" decisions log entry
// ──────────────────────────────────────────────────────────────────────────

it('sets service_id to GMB when the recommendation resolves to GbpAudit', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::RankHigher->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
        'service_id' => null,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    expect($lead->fresh()->service_id)->toBe($gmb->id);
});

it('sets service_id to Website Design & Development when the recommendation resolves to WebsiteGrowthAudit', function () {
    $website = Service::factory()->create(['name' => 'Website Design & Development', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::GrowBusiness->value,
        'budget_range' => LeadBudgetRange::ThreeToSix->value,
        'service_id' => null,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    expect($lead->fresh()->service_id)->toBe($website->id);
});

it('sets service_id to Performance Marketing when the recommendation resolves to LeadGenerationAudit', function () {
    $performanceMarketing = Service::factory()->create(['name' => 'Performance Marketing', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::GenerateLeads->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
        'service_id' => null,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    expect($lead->fresh()->service_id)->toBe($performanceMarketing->id);
});

it('leaves service_id untouched when the recommendation resolves to GrowthStrategy, which spans multiple services', function () {
    $habitual = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::GenerateLeads->value,
        'budget_range' => LeadBudgetRange::SixToTwelve->value,
        'service_id' => $habitual->id,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    expect($lead->fresh()->service_id)->toBe($habitual->id);
});

it('overwrites an existing habitual service_id when the recommendation resolves to a different, mapped offer', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $website = Service::factory()->create(['name' => 'Website Design & Development', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::GrowBusiness->value,
        'budget_range' => LeadBudgetRange::ThreeToSix->value,
        'service_id' => $gmb->id,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    expect($lead->fresh()->service_id)->toBe($website->id);
});

it('does not re-touch a staff-corrected service_id on a later call once the recommendation itself is unchanged', function () {
    Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::RankHigher->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
        'service_id' => null,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    // Staff manually overrides the auto-derived tag with real information.
    $lead->fresh()->forceFill(['service_id' => $seo->id])->saveQuietly();

    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    expect($lead->fresh()->service_id)->toBe($seo->id);
});

it('resolves GMB via the resilient GMB/GMB Services name lookup, matching gmbServiceId()\'s own precedent', function () {
    $renamed = Service::factory()->create(['name' => 'GMB Services', 'is_active' => true]);
    $lead = metaLeadWithoutRecommendation([
        'goal' => LeadGoal::RankHigher->value,
        'budget_range' => LeadBudgetRange::Under3000->value,
        'service_id' => null,
    ]);

    app(GenerateLeadRecommendation::class)->handle($lead);

    expect($lead->fresh()->service_id)->toBe($renamed->id);
});
