<?php

use App\Enums\LeadBudgetBand;
use App\Enums\LeadUrgency;
use App\Enums\UserRole;
use App\Jobs\ScoreLead;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Notifications\HotLeadNotification;
use App\Services\AnthropicClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function fakeClaude(
    int $score = 82,
    string $reason = 'Strong fit, clear budget',
    ?string $budgetBand = 'high',
    ?string $urgency = 'medium',
    ?string $serviceFit = 'Good fit for Website Design & Development',
): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(array_filter([
                'score' => $score,
                'reason' => $reason,
                'budget_band' => $budgetBand,
                'urgency' => $urgency,
                'service_fit' => $serviceFit,
            ], fn ($v) => $v !== null))]],
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

it('stores the score, reason, qualification fields and timestamp, and records usage', function () {
    enableAi();
    fakeClaude(score: 60, reason: 'Hot lead', budgetBand: 'high', urgency: 'medium', serviceFit: 'Solid fit');
    $service = Service::factory()->create();
    $lead = Lead::factory()->create(['service_id' => $service->id]);

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    $lead->refresh();
    expect($lead->ai_score)->toBe(60)
        ->and($lead->ai_score_reason)->toBe('Hot lead')
        ->and($lead->ai_scored_at)->not->toBeNull()
        ->and($lead->ai_budget_band)->toBe(LeadBudgetBand::High)
        ->and($lead->ai_urgency)->toBe(LeadUrgency::Medium)
        ->and($lead->ai_service_fit)->toBe('Solid fit');

    expect(AiUsage::where('feature', 'lead_scoring')->first())
        ->input_tokens->toBe(120)
        ->output_tokens->toBe(25);
});

it('ignores an unrecognised budget band or urgency value instead of failing the whole parse', function () {
    enableAi();
    fakeClaude(score: 55, budgetBand: 'astronomical', urgency: 'yesterday');
    $lead = Lead::factory()->create();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    $lead->refresh();
    expect($lead->ai_score)->toBe(55)
        ->and($lead->ai_budget_band)->toBeNull()
        ->and($lead->ai_urgency)->toBeNull();
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

it('notifies the owner immediately when a lead scores at or above the hot threshold', function () {
    Notification::fake();
    enableAi();
    fakeClaude(score: 70);
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Notification::assertSentTo($owner, HotLeadNotification::class);
});

it('does not send a hot-lead notification for a lead below the threshold', function () {
    Notification::fake();
    enableAi();
    fakeClaude(score: 69);
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Notification::assertNotSentTo($owner, HotLeadNotification::class);
});

it('does not send a hot-lead notification when the lead has no owner', function () {
    Notification::fake();
    enableAi();
    fakeClaude(score: 95);
    $lead = Lead::factory()->create(); // no active Sales user exists, so auto-assign is a no-op

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Notification::assertNothingSent();
});
