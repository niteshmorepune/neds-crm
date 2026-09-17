<?php

use App\Actions\GenerateLeadRecommendation;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Jobs\AnalyzeLeadNextAction;
use App\Jobs\DetectCallFollowUpCommitment;
use App\Jobs\DetectLeadNoteFollowUpCommitment;
use App\Livewire\MeetingImport;
use App\Livewire\RecordNotes;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\GoogleAccountConnection;
use App\Models\Lead;
use App\Models\User;
use App\Services\AnthropicClient;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Every real dispatch site for AnalyzeLeadNextAction, per its own docblock
 * and Lead::queueNextActionAnalysis()'s. Mirrors LeadScoringTest's own
 * dispatch-site coverage of ScoreLead, for the same class of change.
 */
function enableAiForDispatchTests(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function coldCallLeadForDispatch(array $attributes = []): Lead
{
    return Lead::factory()->create(['source' => LeadSource::ColdCall, ...$attributes]);
}

it('dispatches when a call is logged against a Lead', function () {
    $this->seed(MenuItemsSeeder::class);
    enableAiForDispatchTests();
    $lead = coldCallLeadForDispatch();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    $this->actingAs($manager)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d\TH:i'),
        'notes' => 'Good call.',
    ]);

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch when AI is disabled, even for a real call log', function () {
    $this->seed(MenuItemsSeeder::class);
    config(['services.anthropic.enabled' => false]);
    $lead = coldCallLeadForDispatch();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    $this->actingAs($manager)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d\TH:i'),
        'notes' => 'Good call.',
    ]);

    Queue::assertNotPushed(AnalyzeLeadNextAction::class);
});

it('dispatches when a plain note is added to a Lead', function () {
    enableAiForDispatchTests();
    $lead = coldCallLeadForDispatch();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    Livewire::actingAs($manager)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'He asked to be called back tomorrow.')
        ->call('addNote');

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches when a note is logged as a call ("This was a call" shortcut)', function () {
    enableAiForDispatchTests();
    $lead = coldCallLeadForDispatch();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    Livewire::actingAs($manager)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Spoke to him, will call again in 2 days.')
        ->set('logAsCall', true)
        ->set('callOutcome', 'connected')
        ->call('addNote');

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch for a note added to a Deal', function () {
    enableAiForDispatchTests();
    $deal = Deal::factory()->create();
    $manager = User::factory()->role(UserRole::Manager)->create();

    Queue::fake();
    Livewire::actingAs($manager)
        ->test(RecordNotes::class, ['record' => $deal, 'canManage' => true])
        ->set('body', 'A note on a deal.')
        ->call('addNote');

    Queue::assertNotPushed(AnalyzeLeadNextAction::class);
});

it('dispatches when a relevant field changes via LeadObserver', function (string $field, mixed $value) {
    enableAiForDispatchTests();
    $lead = coldCallLeadForDispatch(['status' => LeadStatus::Contacted]);

    Queue::fake();
    $lead->update([$field => $value]);

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
})->with([
    'stall_reason' => ['stall_reason', StallReason::Budget],
    'goal' => ['goal', LeadGoal::GenerateLeads],
    'budget_range' => ['budget_range', LeadBudgetRange::Under3000],
    'website_url' => ['website_url', 'https://example.com'],
    'gbp_url' => ['gbp_url', 'https://maps.google.com/example'],
    'next_follow_up_at' => ['next_follow_up_at', now()->addDay()],
    'status' => ['status', LeadStatus::Qualified],
]);

it('does not dispatch when an irrelevant field changes', function () {
    enableAiForDispatchTests();
    $lead = coldCallLeadForDispatch();

    Queue::fake();
    $lead->update(['company' => 'Renamed Pvt Ltd']);

    Queue::assertNotPushed(AnalyzeLeadNextAction::class);
});

it('dispatches directly from GenerateLeadRecommendation when the recommendation genuinely changes', function () {
    enableAiForDispatchTests();
    $lead = Lead::factory()->create(['goal' => null, 'budget_range' => null]);

    Queue::fake();
    $lead->forceFill([
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ])->saveQuietly();
    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not re-dispatch from GenerateLeadRecommendation on an unchanged recommendation', function () {
    enableAiForDispatchTests();
    $lead = Lead::factory()->create(['goal' => null, 'budget_range' => null]);
    $lead->forceFill([
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ])->saveQuietly();
    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    Queue::fake();
    app(GenerateLeadRecommendation::class)->handle($lead->fresh());

    Queue::assertNotPushed(AnalyzeLeadNextAction::class);
});

it('dispatches after DetectCallFollowUpCommitment successfully sets a commitment', function () {
    enableAiForDispatchTests();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['has_commitment' => true, 'follow_up_in_days' => 2, 'next_action' => 'Send proposal'])]],
            'usage' => ['input_tokens' => 90, 'output_tokens' => 20],
        ]),
    ]);
    $lead = coldCallLeadForDispatch();
    $call = $lead->callLogs()->create([
        'user_id' => User::factory()->create()->id,
        'direction' => 'outgoing', 'outcome' => 'connected',
        'notes' => 'We will send a proposal.', 'called_at' => now(),
    ]);

    Queue::fake();
    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch after DetectCallFollowUpCommitment finds no commitment', function () {
    enableAiForDispatchTests();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['has_commitment' => false, 'follow_up_in_days' => null, 'next_action' => null])]],
            'usage' => ['input_tokens' => 90, 'output_tokens' => 20],
        ]),
    ]);
    $lead = coldCallLeadForDispatch();
    $call = $lead->callLogs()->create([
        'user_id' => User::factory()->create()->id,
        'direction' => 'outgoing', 'outcome' => 'connected',
        'notes' => 'Just a general chat.', 'called_at' => now(),
    ]);

    Queue::fake();
    (new DetectCallFollowUpCommitment($call->id))->handle(app(AnthropicClient::class));

    Queue::assertNotPushed(AnalyzeLeadNextAction::class);
});

it('dispatches after DetectLeadNoteFollowUpCommitment successfully sets a commitment', function () {
    enableAiForDispatchTests();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['has_commitment' => true, 'follow_up_in_days' => 2, 'next_action' => 'Confirm office visit time'])]],
            'usage' => ['input_tokens' => 90, 'output_tokens' => 20],
        ]),
    ]);
    $lead = coldCallLeadForDispatch(['next_follow_up_at' => null]);
    $note = $lead->notes()->create(['user_id' => User::factory()->create()->id, 'body' => 'He said he will visit the office Saturday.']);

    Queue::fake();
    (new DetectLeadNoteFollowUpCommitment($note->id))->handle(app(AnthropicClient::class));

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches when a manual/external meeting is logged for a Lead, but not for a Customer', function () {
    enableAiForDispatchTests();
    $lead = coldCallLeadForDispatch();
    $customer = Customer::factory()->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    Queue::fake();
    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $lead, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', 'zoom')
        ->set('manualOccurredAt', now()->format('Y-m-d\TH:i'))
        ->call('saveManualMeeting');

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);

    Queue::fake();
    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', 'zoom')
        ->set('manualOccurredAt', now()->format('Y-m-d\TH:i'))
        ->call('saveManualMeeting');

    Queue::assertNotPushed(AnalyzeLeadNextAction::class);
});

it('dispatches when a Google Meet is scheduled for a Lead via createMeeting', function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
    ]);
    enableAiForDispatchTests();
    $admin = User::factory()->role(UserRole::Admin)->create();
    GoogleAccountConnection::factory()->create(['user_id' => $admin->id, 'expires_at' => now()->addHour()]);
    $lead = coldCallLeadForDispatch(['email' => 'lead@example.com']);
    $sales = User::factory()->role(UserRole::Sales)->create();

    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'evt-123',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
        ]),
    ]);

    Queue::fake();
    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $lead, 'canManage' => true])
        ->call('openScheduler')
        ->set('scheduleAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('createMeeting');

    Queue::assertPushed(AnalyzeLeadNextAction::class, fn ($job) => $job->leadId === $lead->id);
});
