<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Http\Controllers\OfferRecommendationController;
use App\Models\Lead;
use App\Models\OfferFunnelEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function leadWithRecommendation(array $overrides = []): Lead
{
    return Lead::factory()->create(array_merge([
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ], $overrides));
}

it('renders the recommendation page for a lead reached by its own token', function () {
    $lead = leadWithRecommendation();

    $response = $this->get($lead->recommendationUrl())->assertOk();

    $response->assertSee('Lead Generation Audit')
        ->assertSee('₹299');
});

it('is noindex/nofollow', function () {
    $lead = leadWithRecommendation();

    $this->get($lead->recommendationUrl())->assertSee('noindex, nofollow', false);
});

it('generates and persists a token lazily on first access, idempotently', function () {
    $lead = leadWithRecommendation();
    expect($lead->recommendation_token)->toBeNull();

    $url = $lead->recommendationUrl();
    $tokenAfterFirstCall = $lead->fresh()->recommendation_token;

    expect($tokenAfterFirstCall)->not->toBeNull();
    expect($lead->recommendationUrl())->toBe($url);
});

it('marks recommendation_viewed_at only once, on first real view', function () {
    $lead = leadWithRecommendation();
    $url = $lead->recommendationUrl();

    expect($lead->fresh()->recommendation_viewed_at)->toBeNull();

    $this->get($url);
    $firstViewedAt = $lead->fresh()->recommendation_viewed_at;
    expect($firstViewedAt)->not->toBeNull();

    Carbon::setTestNow(now()->addMinutes(5));
    $this->get($url);
    Carbon::setTestNow();

    expect($lead->fresh()->recommendation_viewed_at->equalTo($firstViewedAt))->toBeTrue();
});

it('logs a recommendation_viewed funnel event on every real view', function () {
    $lead = leadWithRecommendation();
    $url = $lead->recommendationUrl();

    $this->get($url);
    $this->get($url);

    expect(OfferFunnelEvent::where('lead_id', $lead->id)->where('event_type', 'recommendation_viewed')->count())->toBe(2);
});

it('never leaks another lead\'s data when given a different lead\'s numeric id instead of its token', function () {
    $lead = leadWithRecommendation(['name' => 'Real Lead Name']);
    $lead->recommendationUrl();

    // The route only accepts a token, not a bare id — a numeric id is simply
    // not a valid UUID and never resolves to any lead.
    $response = $this->get(route('offers.recommendation', (string) $lead->id));

    $response->assertNotFound();
    $response->assertDontSee('Real Lead Name');
});

it('shows a graceful fallback page for an unknown token, not a 500', function () {
    $this->get(route('offers.recommendation', 'not-a-real-token'))
        ->assertNotFound()
        ->assertSee('recommendation');
});

it('shows the Marketing WhatsApp line, never Support, on the fallback page', function () {
    config(['services.wadesk.support_number' => '918007733737', 'services.wadesk.marketing_number' => '919112095202']);

    $response = $this->get(route('offers.recommendation', 'not-a-real-token'))->assertNotFound();

    $response->assertSee('wa.me/919112095202', false);
    $response->assertDontSee('918007733737', false);
});

it('shows a graceful fallback for a lead with a token but no goal/budget yet', function () {
    $lead = Lead::factory()->create(['goal' => null, 'budget_range' => null]);
    $token = $lead->recommendationUrl();

    $this->get($token)->assertNotFound();
});

it('shows the lead\'s first name in the hero when available', function () {
    $lead = leadWithRecommendation(['name' => 'Priya Sharma']);

    $this->get($lead->recommendationUrl())->assertSee('Priya');
});

// ──────────────────────────────────────────────────────────────────────────
// Dev/QA test mode
// ──────────────────────────────────────────────────────────────────────────

it('the test-mode route resolves the correct recommendation from query params outside production', function () {
    app()->detectEnvironment(fn () => 'testing');

    $this->get(route('offers.recommendation.test', ['goal' => 'rank-google', 'budget' => 'under-3000']))
        ->assertOk()
        ->assertSee('GBP')
        ->assertSee('₹120');
});

it('404s the test-mode route in production', function () {
    // Not a full HTTP round-trip: the production ForceHttps middleware would
    // otherwise redirect plain-http test requests before ever reaching the
    // controller, which is a real but unrelated effect of forcing production
    // for this test. This isolates exactly the behavior being tested.
    app()->detectEnvironment(fn () => 'production');

    $request = Request::create('/offers/recommendation/test', 'GET', [
        'goal' => 'generate-leads', 'budget' => 'under-3000',
    ]);

    expect(fn () => app(OfferRecommendationController::class)->test($request))
        ->toThrow(NotFoundHttpException::class);

    app()->detectEnvironment(fn () => 'testing');
});

it('404s the test-mode route for an invalid goal or budget slug', function () {
    app()->detectEnvironment(fn () => 'testing');

    $this->get(route('offers.recommendation.test', ['goal' => 'not-a-real-goal', 'budget' => 'under-3000']))
        ->assertNotFound();
});
