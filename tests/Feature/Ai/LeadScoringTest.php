<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadBudgetBand;
use App\Enums\LeadUrgency;
use App\Enums\UserRole;
use App\Jobs\ScoreLead;
use App\Livewire\RecordNotes;
use App\Models\AiUsage;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Notifications\HotLeadNotification;
use App\Services\AnthropicClient;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

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

    Queue::assertNotPushed(ScoreLead::class);
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

it('sends a null estimated_value as "not provided", never a misleading literal 0.00', function () {
    // Real production case, 2026-08-31: a lead with a genuinely engaged,
    // connected call still scored 15/100 because the prompt read "Estimated
    // value (INR): 0.00" -- indistinguishable from a confirmed worthless
    // deal, when really nobody had ever entered an estimate at all.
    enableAi();
    fakeClaude();
    $lead = Lead::factory()->create(['estimated_value' => null]);

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'Estimated value (INR): not provided')
            && ! str_contains($prompt, '0.00');
    });
});

it('still formats a real estimated_value as a rupee figure', function () {
    enableAi();
    fakeClaude();
    $lead = Lead::factory()->create(['estimated_value' => 1500000]); // paise -> ₹15,000.00

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'Estimated value (INR): 15,000.00');
    });
});

it('includes recent notes and call history in the scoring prompt, most recent first, capped at 5', function () {
    enableAi();
    fakeClaude();
    $lead = Lead::factory()->create();

    // Explicit spaced timestamps -- real notes are typed minutes apart, but
    // factory-created ones in a tight loop can tie to the same second,
    // which would make latest()'s ordering arbitrary rather than reflect
    // this test's intended "most recent" ranking. created_at isn't
    // fillable, so set it via a direct update after create().
    foreach (range(1, 7) as $i) {
        $note = $lead->notes()->create(['user_id' => User::factory()->create()->id, 'body' => "Note number {$i}"]);
        $note->forceFill(['created_at' => now()->addMinutes($i)])->save();
    }
    $lead->callLogs()->create([
        'user_id' => User::factory()->create()->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::Connected,
        'notes' => 'Asked about pricing and agreed to a proposal.',
        'called_at' => now(),
    ]);

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        $prompt = $body['messages'][0]['content'];

        return str_contains($prompt, 'Note number 7') // most recent of the 7 notes
            && ! str_contains($prompt, 'Note number 1') // oldest, beyond the 5-item cap
            && str_contains($prompt, 'Call history')
            && str_contains($prompt, 'Asked about pricing and agreed to a proposal.');
    });
});

it('omits the Notes/Call history sections entirely for a lead with neither', function () {
    enableAi();
    fakeClaude();
    $lead = Lead::factory()->create();

    (new ScoreLead($lead->id))->handle(app(AnthropicClient::class));

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        $prompt = $body['messages'][0]['content'];

        return ! str_contains($prompt, 'Notes') && ! str_contains($prompt, 'Call history');
    });
});

it('re-scores a lead when a note is added to it', function () {
    enableAi();
    $lead = Lead::factory()->create();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    Livewire::actingAs($manager)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Called and discussed requirements.')
        ->call('addNote');

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});

it('re-scores a lead when a call is logged against it', function () {
    $this->seed(MenuItemsSeeder::class);
    enableAi();
    $lead = Lead::factory()->create();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    $this->actingAs($manager)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d\TH:i'),
        'notes' => 'Good call, she wants a proposal.',
    ]);

    Queue::assertPushed(ScoreLead::class, fn (ScoreLead $job) => $job->leadId === $lead->id);
});
