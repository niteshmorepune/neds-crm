<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Jobs\AnalyzeLeadNextAction;
use App\Models\Activity;
use App\Models\AiUsage;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Note;
use App\Services\AnthropicClient;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Support\Facades\Http;

function fakeNextActionClaude(?string $nextAction = 'Call back to confirm the Google Meet time'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['next_action' => $nextAction])]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 15],
        ]),
    ]);
}

function enableAiForNextAction(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function runAnalyzeLeadNextAction(int $leadId): void
{
    (new AnalyzeLeadNextAction($leadId))->handle(app(AnthropicClient::class), app(VisibilityAuditFunnelMetrics::class));
}

it('writes the AI-detected next action and generated-at timestamp', function () {
    enableAiForNextAction();
    fakeNextActionClaude('Retry calling -- last attempt unanswered');
    $lead = Lead::factory()->create(['source' => LeadSource::ColdCall, 'status' => LeadStatus::Contacted]);

    runAnalyzeLeadNextAction($lead->id);

    $lead->refresh();
    expect($lead->ai_next_action_hint)->toBe('Retry calling -- last attempt unanswered')
        ->and($lead->ai_next_action_generated_at)->not->toBeNull();
});

it('folds notes, calls (with outcome), and the goal/budget state into the prompt', function () {
    enableAiForNextAction();
    fakeNextActionClaude();
    $lead = Lead::factory()->create([
        'source' => LeadSource::ColdCall,
        'status' => LeadStatus::Contacted,
        'goal' => LeadGoal::GenerateLeads,
    ]);
    Note::factory()->create(['notable_type' => Lead::class, 'notable_id' => $lead->id, 'body' => 'He asked about pricing.']);
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::NoAnswer,
        'notes' => null, 'called_at' => now(),
    ]);

    runAnalyzeLeadNextAction($lead->id);

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'He asked about pricing.')
            && str_contains($prompt, 'No Answer')
            && str_contains($prompt, 'Generate More Leads');
    });
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $lead = Lead::factory()->create();

    runAnalyzeLeadNextAction($lead->id);

    Http::assertNothingSent();
    expect($lead->refresh()->ai_next_action_hint)->toBeNull();
});

it('skips a Lost lead entirely', function () {
    // Lead created with AI still OFF, so LeadObserver's own creation-time
    // ScoreLead dispatch (sync queue in tests) can't sneak in an unrelated
    // HTTP call before Http::assertNothingSent() below checks this job's
    // own behavior specifically.
    $lead = Lead::factory()->create(['status' => LeadStatus::Lost]);
    enableAiForNextAction();
    Http::fake();

    runAnalyzeLeadNextAction($lead->id);

    Http::assertNothingSent();
});

it('does NOT skip a Converted lead -- that is exactly where send-quotation/schedule-meeting hints matter', function () {
    enableAiForNextAction();
    fakeNextActionClaude('Send the quotation');
    $lead = Lead::factory()->create(['status' => LeadStatus::Converted]);

    runAnalyzeLeadNextAction($lead->id);

    expect($lead->refresh()->ai_next_action_hint)->toBe('Send the quotation');
});

it('leaves an existing hint untouched when the API call fails', function () {
    enableAiForNextAction();
    $lead = Lead::factory()->create();
    $lead->forceFill(['ai_next_action_hint' => 'Previously cached hint'])->saveQuietly();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);

    runAnalyzeLeadNextAction($lead->id);

    expect($lead->refresh()->ai_next_action_hint)->toBe('Previously cached hint');
    expect(AiUsage::count())->toBe(0);
});

it('leaves an existing hint untouched when Claude replies with null', function () {
    enableAiForNextAction();
    $lead = Lead::factory()->create();
    $lead->forceFill(['ai_next_action_hint' => 'Previously cached hint'])->saveQuietly();
    fakeNextActionClaude(null);

    runAnalyzeLeadNextAction($lead->id);

    expect($lead->refresh()->ai_next_action_hint)->toBe('Previously cached hint');
});

it('is a no-op for a deleted lead', function () {
    enableAiForNextAction();
    Http::fake();

    runAnalyzeLeadNextAction(999999);

    Http::assertNothingSent();
});

it('does not fire any model event (activity log stays clean)', function () {
    enableAiForNextAction();
    fakeNextActionClaude();
    $lead = Lead::factory()->create();
    $countBefore = Activity::where('subject_type', Lead::class)->where('subject_id', $lead->id)->count();

    runAnalyzeLeadNextAction($lead->id);

    $countAfter = Activity::where('subject_type', Lead::class)->where('subject_id', $lead->id)->count();
    expect($countAfter)->toBe($countBefore);
});
