<?php

use App\Jobs\ScoreLead;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Service;
use App\Services\AnthropicClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function fakeClaude(int $score = 82, string $reason = 'Strong fit, clear budget'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['score' => $score, 'reason' => $reason])]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 25],
        ]),
    ]);
}

function enableAi(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

it('does not dispatch scoring when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Queue::fake();

    Lead::factory()->create();

    Queue::assertNothingPushed();
});

it('dispatches scoring on lead create when AI is enabled', function () {
    enableAi();
    Queue::fake();

    $lead = Lead::factory()->create();

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});

it('dispatches a re-score when a scoring-relevant field changes', function () {
    enableAi();
    $lead = Lead::factory()->create();

    Queue::fake();
    $lead->update(['company' => 'New Company Pvt Ltd']);

    Queue::assertPushed(ScoreLead::class, 1);
});

it('does not re-score when only a non-scoring field changes', function () {
    enableAi();
    $lead = Lead::factory()->create();

    Queue::fake();
    $lead->update(['next_follow_up_at' => now()->addDay()]);

    Queue::assertNothingPushed();
});

it('stores the score, reason and timestamp, and records usage', function () {
    enableAi();
    fakeClaude(score: 90, reason: 'Hot lead');
    $service = Service::factory()->create();
    $lead = Lead::factory()->create(['service_id' => $service->id]);

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    $lead->refresh();
    expect($lead->ai_score)->toBe(90)
        ->and($lead->ai_score_reason)->toBe('Hot lead')
        ->and($lead->ai_scored_at)->not->toBeNull();

    expect(AiUsage::where('feature', 'lead_scoring')->first())
        ->input_tokens->toBe(120)
        ->output_tokens->toBe(25);
});

it('clamps an out-of-range score into 0-100', function () {
    enableAi();
    fakeClaude(score: 250, reason: 'Over the top');
    $lead = Lead::factory()->create();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    expect($lead->refresh()->ai_score)->toBe(100);
});

it('leaves the lead unscored and never throws when the API fails', function () {
    enableAi();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $lead = Lead::factory()->create();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    expect($lead->refresh()->ai_score)->toBeNull();
    expect(AiUsage::count())->toBe(0);
});

it('writes the score without firing an activity log entry', function () {
    enableAi();
    fakeClaude();
    $lead = Lead::factory()->create();
    $before = $lead->activities()->count();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    expect($lead->activities()->count())->toBe($before);
});

it('handles the job gracefully when AI was turned off after dispatch', function () {
    config(['services.anthropic.enabled' => false]);
    $lead = Lead::factory()->create();
    Http::fake();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
    expect($lead->refresh()->ai_score)->toBeNull();
});
