<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\UserRole;
use App\Jobs\DetectCallFollowUpCommitment;
use App\Models\AiUsage;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CallFollowUpAutoSet;
use App\Services\AnthropicClient;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function fakeCommitmentClaude(bool $hasCommitment = true, ?int $days = 3, ?string $nextAction = 'Send proposal'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'has_commitment' => $hasCommitment,
                'follow_up_in_days' => $hasCommitment ? $days : null,
                'next_action' => $hasCommitment ? $nextAction : null,
            ])]],
            'usage' => ['input_tokens' => 90, 'output_tokens' => 20],
        ]),
    ]);
}

function enableAiForCalls(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

beforeEach(function () {
    $this->owner = User::factory()->role(UserRole::Sales)->create();
    $this->lead = Lead::factory()->ownedBy($this->owner->id)->create();
});

function connectedCallFor(Lead $lead, User $user, string $notes): CallLog
{
    return $lead->callLogs()->create([
        'user_id' => $user->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::Connected,
        'notes' => $notes,
        'called_at' => now(),
    ]);
}

it('sets follow_up_at and next_action when a commitment is detected, and notifies the rep', function () {
    Notification::fake();
    enableAiForCalls();
    fakeCommitmentClaude(days: 3, nextAction: 'Send proposal');
    $call = connectedCallFor($this->lead, $this->owner, 'We will prepare a proposal and explain details in a meeting. She agreed.');

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    $call->refresh();
    expect($call->follow_up_at)->not->toBeNull()
        ->and($call->follow_up_at->toDateString())->toBe($call->called_at->copy()->addDays(3)->toDateString())
        ->and($call->next_action)->toBe('Send proposal');

    Notification::assertSentTo($this->owner, CallFollowUpAutoSet::class);
});

it('does not set anything when Claude finds no commitment', function () {
    enableAiForCalls();
    fakeCommitmentClaude(hasCommitment: false);
    $call = connectedCallFor($this->lead, $this->owner, 'Just a general chat, nothing concrete.');

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    expect($call->refresh()->follow_up_at)->toBeNull();
});

it('never overrides a follow_up_at the rep already set themselves', function () {
    enableAiForCalls();
    fakeCommitmentClaude();
    $manualDate = now()->addDays(10);
    $call = $this->lead->callLogs()->create([
        'user_id' => $this->owner->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::Connected,
        'notes' => 'We will send a proposal.',
        'called_at' => now(),
        'follow_up_at' => $manualDate,
    ]);

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
    expect($call->refresh()->follow_up_at->toDateTimeString())->toBe($manualDate->toDateTimeString());
});

it('never overrides an existing next_action even when it sets follow_up_at', function () {
    enableAiForCalls();
    fakeCommitmentClaude(nextAction: 'AI suggested action');
    $call = $this->lead->callLogs()->create([
        'user_id' => $this->owner->id,
        'direction' => CallDirection::Outgoing,
        'outcome' => CallOutcome::Connected,
        'notes' => 'We will send a proposal.',
        'called_at' => now(),
        'next_action' => 'Rep-entered action',
    ]);

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    expect($call->refresh()->next_action)->toBe('Rep-entered action');
});

it('does nothing when the call has no notes', function () {
    enableAiForCalls();
    Http::fake();
    $call = connectedCallFor($this->lead, $this->owner, '');

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $call = connectedCallFor($this->lead, $this->owner, 'We will send a proposal.');

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
    expect($call->refresh()->follow_up_at)->toBeNull();
});

it('fails silently and leaves the call untouched when the API errors', function () {
    enableAiForCalls();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $call = connectedCallFor($this->lead, $this->owner, 'We will send a proposal.');

    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    expect($call->refresh()->follow_up_at)->toBeNull();
    expect(AiUsage::count())->toBe(0);
});

it('is dispatched when a call is logged with notes and no follow_up_at, but not when a follow_up_at was already set', function () {
    $this->seed(MenuItemsSeeder::class);
    enableAiForCalls();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    $this->actingAs($manager)->post(route('calls.store'), [
        'lead_id' => $this->lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d\TH:i'),
        'notes' => 'We will send a proposal.',
    ]);
    Queue::assertPushed(DetectCallFollowUpCommitment::class);

    Queue::fake();
    $this->actingAs($manager)->post(route('calls.store'), [
        'lead_id' => $this->lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d\TH:i'),
        'notes' => 'We will send a proposal.',
        'follow_up_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ]);
    Queue::assertNotPushed(DetectCallFollowUpCommitment::class);
});
