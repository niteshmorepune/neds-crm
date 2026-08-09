<?php

use App\Enums\MeetingPlatform;
use App\Enums\MeetingSummaryStatus;
use App\Enums\UserRole;
use App\Jobs\SummarizeMeeting;
use App\Livewire\MeetingImport;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('shows the Log External Meeting button even when the Google Meet feature is off', function () {
    config(['services.google_meet.enabled' => false]);
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->assertSee('Log External Meeting');
});

it('logs a manually-entered meeting with a synthetic event id and the given platform', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', MeetingPlatform::Zoom->value)
        ->set('manualTitle', 'Quarterly review')
        ->set('manualOccurredAt', '2026-08-10T15:00')
        ->set('manualDurationMinutes', 45)
        ->set('manualNotes', 'Discussed Q3 roadmap.')
        ->call('saveManualMeeting')
        ->assertHasNoErrors();

    $meeting = Meeting::where('title', 'Quarterly review')->first();
    expect($meeting)->not->toBeNull()
        ->and($meeting->meetable_type)->toBe(Customer::class)
        ->and($meeting->meetable_id)->toBe($customer->id)
        ->and($meeting->platform)->toBe(MeetingPlatform::Zoom)
        ->and($meeting->google_event_id)->toStartWith('manual-')
        ->and($meeting->duration_minutes)->toBe(45)
        ->and($meeting->raw_transcript)->toBe('Discussed Q3 roadmap.')
        ->and($meeting->isGoogleMeetImport())->toBeFalse()
        ->and($meeting->occurred_at->toIso8601String())->toBe('2026-08-10T09:30:00+00:00'); // 15:00 IST = 09:30 UTC
});

it('defaults the title to "{Platform} meeting" when none is given', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', MeetingPlatform::MicrosoftTeams->value)
        ->set('manualOccurredAt', '2026-08-10T15:00')
        ->call('saveManualMeeting')
        ->assertHasNoErrors();

    expect(Meeting::where('platform', MeetingPlatform::MicrosoftTeams->value)->first()->title)
        ->toBe('Microsoft Teams meeting');
});

it('requires a when field to log a manual meeting', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualOccurredAt', '')
        ->call('saveManualMeeting')
        ->assertHasErrors(['manualOccurredAt']);
});

it('does not allow selecting Google Meet as the manual platform', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', MeetingPlatform::GoogleMeet->value)
        ->set('manualOccurredAt', '2026-08-10T15:00')
        ->call('saveManualMeeting')
        ->assertHasErrors(['manualPlatform']);
});

it('queues a summary job for a manual meeting with notes, when AI is enabled', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', MeetingPlatform::Zoom->value)
        ->set('manualOccurredAt', '2026-08-10T15:00')
        ->set('manualNotes', 'Client wants a new proposal.')
        ->call('saveManualMeeting');

    $meeting = Meeting::where('raw_transcript', 'Client wants a new proposal.')->first();
    expect($meeting->ai_summary_status)->toBe(MeetingSummaryStatus::Pending);
    Queue::assertPushed(SummarizeMeeting::class, fn ($job) => $job->meetingId === $meeting->id);
});

it('does not queue a summary job for a manual meeting with no notes', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', MeetingPlatform::Zoom->value)
        ->set('manualOccurredAt', '2026-08-10T15:00')
        ->call('saveManualMeeting');

    Queue::assertNothingPushed();
});

it('blocks logging a manual meeting for a user without manage permission', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => false])
        ->call('openManualForm')
        ->assertStatus(403);
});

it('attaches a manual meeting to a Lead as well as a Customer', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $lead, 'canManage' => true])
        ->call('openManualForm')
        ->set('manualPlatform', MeetingPlatform::Zoom->value)
        ->set('manualOccurredAt', '2026-08-10T15:00')
        ->call('saveManualMeeting');

    $meeting = Meeting::where('platform', MeetingPlatform::Zoom->value)->first();
    expect($meeting->meetable_type)->toBe(Lead::class)
        ->and($meeting->meetable_id)->toBe($lead->id);
});

it('does not offer "sync recording" for a manually-logged meeting, and shows its platform badge', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $customer->meetings()->create([
        'user_id' => $sales->id,
        'google_event_id' => 'manual-abc',
        'platform' => MeetingPlatform::Zoom->value,
        'title' => 'Zoom meeting',
        'occurred_at' => now(),
    ]);

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->assertSee('Zoom')
        ->assertDontSee('Sync recording');
});

it('shows a friendly error instead of calling Google when syncRecording is attempted on a manual meeting', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();
    $meeting = $customer->meetings()->create([
        'user_id' => $sales->id,
        'google_event_id' => 'manual-abc',
        'platform' => MeetingPlatform::Zoom->value,
        'title' => 'Zoom meeting',
        'occurred_at' => now(),
    ]);

    Http::fake();

    Livewire::actingAs($sales)
        ->test(MeetingImport::class, ['record' => $customer, 'canManage' => true])
        ->call('syncRecording', $meeting->id)
        ->assertSee('logged manually');

    Http::assertNothingSent();
});
