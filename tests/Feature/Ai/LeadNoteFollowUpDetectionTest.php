<?php

use App\Enums\UserRole;
use App\Jobs\DetectCallFollowUpCommitment;
use App\Jobs\DetectLeadNoteFollowUpCommitment;
use App\Livewire\RecordNotes;
use App\Models\AiUsage;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use App\Notifications\LeadFollowUpAutoSet;
use App\Services\AnthropicClient;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Plain-note counterpart to CallFollowUpDetectionTest — same fixture shapes,
 * mirrored so the two mechanisms can never silently drift apart in what
 * they promise (never override a rep-entered date, silent no-op on
 * failure/no-commitment/AI-off).
 */
function fakeLeadCommitmentClaude(bool $hasCommitment = true, ?int $days = 3, ?string $nextAction = 'Confirm office visit time'): void
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

function enableAiForLeadNotes(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

beforeEach(function () {
    $this->author = User::factory()->role(UserRole::Sales)->create();
    $this->lead = Lead::factory()->ownedBy($this->author->id)->create(['next_follow_up_at' => null]);
});

function leadNoteFrom(Lead $lead, User $author, string $body): Note
{
    return $lead->notes()->create(['user_id' => $author->id, 'body' => $body]);
}

it('sets next_follow_up_at and ai_detected_next_action when a commitment is detected, and notifies the author', function () {
    Notification::fake();
    enableAiForLeadNotes();
    fakeLeadCommitmentClaude(days: 2, nextAction: 'Confirm office visit time');
    $note = leadNoteFrom($this->lead, $this->author, 'He said he will visit our office on Saturday.');

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    $this->lead->refresh();
    expect($this->lead->next_follow_up_at)->not->toBeNull()
        ->and($this->lead->next_follow_up_at->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and($this->lead->ai_detected_next_action)->toBe('Confirm office visit time');

    Notification::assertSentTo($this->author, LeadFollowUpAutoSet::class);
});

it('does not set anything when Claude finds no commitment', function () {
    enableAiForLeadNotes();
    fakeLeadCommitmentClaude(hasCommitment: false);
    $note = leadNoteFrom($this->lead, $this->author, 'Just a general chat, nothing concrete.');

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    expect($this->lead->refresh()->next_follow_up_at)->toBeNull();
});

it('never overrides a next_follow_up_at the rep already set themselves', function () {
    enableAiForLeadNotes();
    fakeLeadCommitmentClaude();
    $manualDate = now()->addDays(10);
    // Queue::fake() here only to stop this manual update's own, unrelated
    // AnalyzeLeadNextAction re-analysis (a real trigger since 2026-09-18,
    // later — see LeadNextActionAnalysisDispatchTest) from running
    // synchronously under the sync test queue and polluting the
    // Http::assertNothingSent() assertion below, which is about
    // DetectLeadNoteFollowUpCommitment specifically.
    Queue::fake();
    $this->lead->update(['next_follow_up_at' => $manualDate]);
    $note = leadNoteFrom($this->lead, $this->author, 'He will visit the office Saturday.');

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
    expect($this->lead->refresh()->next_follow_up_at->toDateTimeString())->toBe($manualDate->toDateTimeString());
});

it('does nothing when the note is blank', function () {
    enableAiForLeadNotes();
    Http::fake();
    $note = leadNoteFrom($this->lead, $this->author, ' ');

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $note = leadNoteFrom($this->lead, $this->author, 'He will visit the office Saturday.');

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
    expect($this->lead->refresh()->next_follow_up_at)->toBeNull();
});

it('fails silently and leaves the lead untouched when the API errors', function () {
    enableAiForLeadNotes();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $note = leadNoteFrom($this->lead, $this->author, 'He will visit the office Saturday.');

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    expect($this->lead->refresh()->next_follow_up_at)->toBeNull();
    expect(AiUsage::count())->toBe(0);
});

it('does nothing for a note on a Deal, not a Lead', function () {
    enableAiForLeadNotes();
    Http::fake();
    $deal = Deal::factory()->create();
    $note = $deal->notes()->create(['user_id' => $this->author->id, 'body' => 'He will visit the office Saturday.']);

    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    Http::assertNothingSent();
});

it('is dispatched when a plain note is added to a Lead with no follow-up set, but not when already scheduled', function () {
    $this->seed(MenuItemsSeeder::class);
    enableAiForLeadNotes();
    $manager = User::factory()->role(UserRole::Manager)->create();

    // Plain note, no existing follow-up -> dispatched.
    Queue::fake();
    Livewire::actingAs($manager)->test(RecordNotes::class, ['record' => $this->lead, 'canManage' => true])
        ->set('body', 'He will visit the office Saturday.')
        ->call('addNote');
    Queue::assertPushed(DetectLeadNoteFollowUpCommitment::class);

    // Already has a follow-up scheduled -> not dispatched.
    $this->lead->update(['next_follow_up_at' => now()->addDays(3)]);
    Queue::fake();
    Livewire::actingAs($manager)->test(RecordNotes::class, ['record' => $this->lead->fresh(), 'canManage' => true])
        ->set('body', 'Another note.')
        ->call('addNote');
    Queue::assertNotPushed(DetectLeadNoteFollowUpCommitment::class);
});

it('dispatches the CallLog job instead, when the note is logged as a call', function () {
    $this->seed(MenuItemsSeeder::class);
    enableAiForLeadNotes();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    Livewire::actingAs($manager)->test(RecordNotes::class, ['record' => $this->lead, 'canManage' => true])
        ->set('body', 'Called and he agreed to send a proposal.')
        ->set('logAsCall', true)
        ->set('callOutcome', 'connected')
        ->call('addNote');

    Queue::assertPushed(DetectCallFollowUpCommitment::class);
    Queue::assertNotPushed(DetectLeadNoteFollowUpCommitment::class);
});
