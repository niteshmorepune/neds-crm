<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Services\AiAssistant;
use Illuminate\Support\Facades\Http;

function fakeLostReasonClaude(?string $reason = 'competitor', ?string $rationale = 'Mentioned choosing a rival agency in the last call.'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['reason' => $reason, 'rationale' => $rationale])]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 20],
        ]),
    ]);
}

function enableAiForLostReason(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

it('returns null and makes no call when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'Client pushed back on price.']);

    expect(app(AiAssistant::class)->suggestDealLostReason($deal))->toBeNull();
    Http::assertNothingSent();
});

it('returns null and makes no call when the deal has no history at all', function () {
    enableAiForLostReason();
    Http::fake();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(); // no lead_id, no notes

    expect(app(AiAssistant::class)->suggestDealLostReason($deal))->toBeNull();
    Http::assertNothingSent();
});

it('suggests a reason from the deal\'s own notes', function () {
    enableAiForLostReason();
    fakeLostReasonClaude();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'They said a rival agency quoted lower.']);

    $result = app(AiAssistant::class)->suggestDealLostReason($deal);

    expect($result['reason'])->toBe(DealLostReason::Competitor)
        ->and($result['rationale'])->toBe('Mentioned choosing a rival agency in the last call.');
});

it('folds in the originating lead\'s notes and calls when the deal was converted from one', function () {
    enableAiForLostReason();
    fakeLostReasonClaude();
    $lead = Lead::factory()->create();
    $lead->notes()->create(['user_id' => null, 'body' => 'Asked about pricing tiers.']);
    $lead->callLogs()->create([
        'user_id' => User::factory()->create()->id, 'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::Connected,
        'notes' => 'Said our price was too high compared to a competitor.', 'called_at' => now(),
    ]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['lead_id' => $lead->id]); // no notes of its own

    app(AiAssistant::class)->suggestDealLostReason($deal);

    Http::assertSent(function ($request) {
        $prompt = json_decode($request->body(), true)['messages'][0]['content'];

        return str_contains($prompt, 'Asked about pricing tiers.')
            && str_contains($prompt, 'Said our price was too high compared to a competitor.');
    });
});

it('does not call Claude for a deal with no notes of its own and a lead with no history either', function () {
    // Lead created before AI is enabled, and before Http::fake(), so its own
    // ScoreLead dispatch (LeadObserver::created(), sync queue) never fires and
    // can't be mistaken for the call this test is actually checking for.
    $lead = Lead::factory()->create(); // no notes, no calls
    enableAiForLostReason();
    Http::fake();
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create(['lead_id' => $lead->id]);

    expect(app(AiAssistant::class)->suggestDealLostReason($deal))->toBeNull();
    Http::assertNothingSent();
});

it('returns a null reason alongside a rationale when Claude finds no clear signal', function () {
    enableAiForLostReason();
    fakeLostReasonClaude(reason: null, rationale: 'Only one vague note, not enough to tell.');
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'Talked briefly.']);

    $result = app(AiAssistant::class)->suggestDealLostReason($deal);

    expect($result['reason'])->toBeNull()
        ->and($result['rationale'])->toBe('Only one vague note, not enough to tell.');
});

it('returns null when Claude\'s reply cannot be parsed', function () {
    enableAiForLostReason();
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'not json at all']],
        'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
    ])]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'Some note.']);

    expect(app(AiAssistant::class)->suggestDealLostReason($deal))->toBeNull();
});

it('returns null when the API call fails', function () {
    enableAiForLostReason();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
    $deal->notes()->create(['user_id' => null, 'body' => 'Some note.']);

    expect(app(AiAssistant::class)->suggestDealLostReason($deal))->toBeNull();
});
