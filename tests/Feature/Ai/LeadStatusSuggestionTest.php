<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Support\Facades\Http;

function fakeLeadStatusClaude(?string $status = 'contacted', ?string $rationale = 'Rep called but the client did not pick up.'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['status' => $status, 'rationale' => $rationale])]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 20],
        ]),
    ]);
}

function enableAiForLeadStatus(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

it('returns null and makes no call when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    expect(app(AiAssistant::class)->suggestLeadStatusUpdate($lead))->toBeNull();
    Http::assertNothingSent();
});

it('returns null and makes no call for a lead that is not actually flagged stale (no notes/calls)', function () {
    // Created before AI is enabled and before Http::fake(), so its own
    // ScoreLead dispatch (LeadObserver::created(), sync queue) never fires
    // and can't be mistaken for the call this test is actually checking for
    // — same precedent as DealLostReasonSuggestionTest's equivalent guard.
    $lead = Lead::factory()->create(['status' => LeadStatus::New]); // genuinely untouched
    enableAiForLeadStatus();
    Http::fake();

    expect(app(AiAssistant::class)->suggestLeadStatusUpdate($lead))->toBeNull();
    Http::assertNothingSent();
});

it('returns null and makes no call for a lead already past New, even with notes', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    enableAiForLeadStatus();
    Http::fake();
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    expect(app(AiAssistant::class)->suggestLeadStatusUpdate($lead))->toBeNull();
    Http::assertNothingSent();
});

it('suggests Contacted from a note describing an unanswered call attempt', function () {
    enableAiForLeadStatus();
    fakeLeadStatusClaude();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'The call was not answered by the client.']);

    $result = app(AiAssistant::class)->suggestLeadStatusUpdate($lead);

    expect($result['status'])->toBe(LeadStatus::Contacted)
        ->and($result['rationale'])->toBe('Rep called but the client did not pick up.');
});

it('sends the lead\'s own call log history in the prompt', function () {
    enableAiForLeadStatus();
    fakeLeadStatusClaude();
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->callLogs()->create([
        'user_id' => User::factory()->create()->id, 'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::NoAnswer,
        'notes' => 'Tried twice, no pickup.', 'called_at' => now(),
    ]);

    app(AiAssistant::class)->suggestLeadStatusUpdate($lead);

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'Tried twice, no pickup.');
    });
});

it('accepts a Qualified suggestion when the model returns one', function () {
    enableAiForLeadStatus();
    fakeLeadStatusClaude(status: 'qualified', rationale: 'They asked for a formal quote.');
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Client asked us to send a proposal.']);

    $result = app(AiAssistant::class)->suggestLeadStatusUpdate($lead);

    expect($result['status'])->toBe(LeadStatus::Qualified);
});

it('accepts a Lost suggestion when the model returns one', function () {
    enableAiForLeadStatus();
    fakeLeadStatusClaude(status: 'lost', rationale: 'They explicitly said not interested and asked to stop calling.');
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Client said not interested, please stop calling.']);

    $result = app(AiAssistant::class)->suggestLeadStatusUpdate($lead);

    expect($result['status'])->toBe(LeadStatus::Lost);
});

it('discards a hallucinated status outside the 3 offered (e.g. "new" or "converted")', function () {
    enableAiForLeadStatus();
    fakeLeadStatusClaude(status: 'converted', rationale: 'Nonsense suggestion.');
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    $result = app(AiAssistant::class)->suggestLeadStatusUpdate($lead);

    expect($result['status'])->toBeNull()
        ->and($result['rationale'])->toBe('Nonsense suggestion.');
});

it('returns null when Claude\'s reply cannot be parsed', function () {
    enableAiForLeadStatus();
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'not json at all']],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ])]);
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    expect(app(AiAssistant::class)->suggestLeadStatusUpdate($lead))->toBeNull();
});

it('returns null when the API call fails', function () {
    enableAiForLeadStatus();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Called, no answer.']);

    expect(app(AiAssistant::class)->suggestLeadStatusUpdate($lead))->toBeNull();
});
