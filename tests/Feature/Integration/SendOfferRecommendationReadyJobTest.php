<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.offer_recommendation_template_name' => 'offer_recommendation_ready',
    ]);
    Queue::fake();
});

function leadWithNonGbpRecommendation(array $overrides = []): Lead
{
    $lead = Lead::factory()->create(array_merge([
        'name' => 'Priya Shah',
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ], $overrides));

    // Force-generate the recommendation/token directly, bypassing the
    // action's own auto-dispatch (already covered by GenerateLeadRecommendationTest)
    // so these tests isolate the job's own send behavior.
    $lead->forceFill([
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => (string) Str::uuid(),
        'recommendation_generated_at' => now(),
    ])->saveQuietly();

    return $lead->fresh();
}

it('sends the recommendation-ready template with the recommendation token and marks the lead notified', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1', 'messageId' => 'wamsg_1'], 201)]);

    $lead = leadWithNonGbpRecommendation();

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'offer_recommendation_ready'
            && $request['variables'] === ['Priya Shah']
            && $request['buttonUrlParam'] === $lead->recommendation_token;
    });

    expect($lead->fresh()->recommendation_notified_at)->not->toBeNull();
    expect($lead->fresh()->notes()->where('body', 'like', '%recommendation-ready%')->exists())->toBeTrue();
});

it('logs nothing and does not throw when wadesk.in returns non-2xx, and does not mark the lead notified', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'bad'], 500)]);

    $lead = leadWithNonGbpRecommendation();

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    expect($lead->fresh()->recommendation_notified_at)->toBeNull();
});

it('does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $lead = leadWithNonGbpRecommendation();

    expect(fn () => (new SendOfferRecommendationReadyJob($lead->id))->handle())->not->toThrow(Throwable::class);
    expect($lead->fresh()->recommendation_notified_at)->toBeNull();
});

it('marks the lead notified but adds no note when wadesk.in skips an opted-out contact', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['skipped' => true, 'reason' => 'opted_out'], 200)]);

    $lead = leadWithNonGbpRecommendation();

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    expect($lead->fresh()->recommendation_notified_at)->not->toBeNull();
    expect($lead->notes()->count())->toBe(0);
});

it('never sends twice for the same lead', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithNonGbpRecommendation(['recommendation_notified_at' => now()]);

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips sending when staff has already replied to the lead over WhatsApp', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithNonGbpRecommendation();
    $lead->notes()->create(['body' => "[Sent via WhatsApp by Kiran Katte]\nHappy to help!"]);

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertNothingSent();
    expect($lead->fresh()->recommendation_notified_at)->toBeNull();
});

it('does not skip for the AI after-hours assistant\'s own auto-reply', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $lead = leadWithNonGbpRecommendation();
    $lead->notes()->create(['body' => "[Sent via WhatsApp by AI Assistant (auto-reply)]\nThanks for reaching out."]);

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/send-template');
});

it('no-ops when the template is not configured', function () {
    config(['services.wadesk.offer_recommendation_template_name' => null]);
    Http::fake();

    $lead = leadWithNonGbpRecommendation();

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('no-ops when the lead has no phone', function () {
    Http::fake();

    $lead = leadWithNonGbpRecommendation(['phone' => null]);

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('no-ops when the lead has no recommendation token yet', function () {
    Http::fake();

    $lead = Lead::factory()->create([
        'phone' => '+91 98765 43210',
        'meta_leadgen_id' => 'lg_'.uniqid(),
        'recommendation_token' => null,
    ]);

    (new SendOfferRecommendationReadyJob($lead->id))->handle();

    Http::assertNothingSent();
});
