<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferKey;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // LeadObserver/GenerateLeadRecommendation dispatch several jobs
    // (SyncLeadToWadeskJob, ScoreLead, SendOfferRecommendationReadyJob
    // itself) the moment a metaLead() factory below creates a lead with a
    // resolved recommendation — faking the queue here, then re-faking right
    // before each test's own Artisan::call(), discards those creation-time
    // dispatches so assertions only ever see this command's own dispatch.
    // Same pattern as RetryFailedLeadWelcomeMessagesTest/VisibilityAuditFirstInviteTest.
    Queue::fake();
});

function strandedLead(array $attributes = []): Lead
{
    return Lead::factory()->create(array_merge([
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_offer_key' => OfferKey::LeadGenerationAudit->value,
        'recommendation_generated_at' => now()->subHours(3),
        'recommendation_notified_at' => null,
        'recommendation_retry_attempted_at' => null,
    ], $attributes));
}

it('retries a stranded lead past the minimum wait, with no prior retry and no staff engagement', function () {
    $lead = strandedLead();

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    Queue::assertPushed(SendOfferRecommendationReadyJob::class, fn ($job) => $job->leadId === $lead->id);
    expect($lead->fresh()->recommendation_retry_attempted_at)->not->toBeNull();
});

it('does not retry before the minimum wait has passed', function () {
    strandedLead(['recommendation_generated_at' => now()->subMinutes(30)]);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('does not retry a lead whose original send already succeeded', function () {
    strandedLead(['recommendation_notified_at' => now()->subHours(1)]);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('does not retry a lead a second time', function () {
    strandedLead(['recommendation_retry_attempted_at' => now()->subHours(2)]);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('is idempotent across repeated runs -- a lead retried on the first run is skipped on the second', function () {
    $lead = strandedLead();

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');
    Queue::assertPushed(SendOfferRecommendationReadyJob::class, fn ($job) => $job->leadId === $lead->id);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('skips a lead staff has already engaged with since the recommendation was generated', function () {
    $lead = strandedLead();
    $lead->callLogs()->create([
        'user_id' => User::factory()->create()->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::NoAnswer,
        'called_at' => now()->subMinutes(30),
    ]);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
    // Not marked as retried either -- a human is handling it, this command
    // took no automated action on it at all.
    expect($lead->fresh()->recommendation_retry_attempted_at)->toBeNull();
});

it('does not retry a GBP-recommended lead -- that offer keeps its own untouched VA invite pipeline', function () {
    // meta_leadgen_id null so LeadObserver's own routeMetaLeadFirstTouch()
    // never re-runs GenerateLeadRecommendation on creation and overwrites
    // the manually-set recommendation_offer_key below back to whatever the
    // goal/budget combo would otherwise resolve to.
    strandedLead(['meta_leadgen_id' => null, 'recommendation_offer_key' => OfferKey::GbpAudit->value]);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});

it('reports accurate summary counts', function () {
    strandedLead(); // retried
    strandedLead(); // retried
    $engaged = strandedLead();
    $engaged->callLogs()->create([
        'user_id' => User::factory()->create()->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::Connected,
        'called_at' => now()->subMinutes(10),
    ]);

    Queue::fake();
    Artisan::call('app:retry-stranded-offer-recommendation-messages');

    expect(Artisan::output())
        ->toContain('Retried 2 stranded recommendation message(s)')
        ->toContain('skipped 1 (staff already engaged)')
        ->toContain('out of 3 candidate(s)');
});
