<?php

use App\Enums\MeetingSummaryStatus;
use App\Jobs\SummarizeMeeting;
use App\Livewire\MeetingSummary;
use App\Models\Meeting;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
        'services.anthropic.enabled' => true,
        'services.anthropic.key' => 'sk-test',
    ]);
});

it('shows a summarize button for a meeting with a transcript but no summary yet', function () {
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew.']);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => true])
        ->assertSee('Summarize with AI');
});

it('dispatches the summarize job on click and shows a queued state', function () {
    Queue::fake();
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew.']);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => true])
        ->call('summarize')
        ->assertSet('status', 'pending');

    Queue::assertPushed(SummarizeMeeting::class, fn ($job) => $job->meetingId === $meeting->id);
});

it('shows the completed summary', function () {
    $meeting = Meeting::factory()->create([
        'raw_transcript' => 'Client: lets renew.',
        'ai_summary_status' => MeetingSummaryStatus::Completed,
        'ai_summary' => 'Key points: renewal discussed.',
    ]);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => true])
        ->assertSee('Key points: renewal discussed.');
});

it('shows a retry option when summarizing failed', function () {
    $meeting = Meeting::factory()->create([
        'raw_transcript' => 'Client: lets renew.',
        'ai_summary_status' => MeetingSummaryStatus::Failed,
    ]);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => true])
        ->assertSee('Summary failed')
        ->assertSee('Retry');
});

it('hides everything when there is no transcript yet', function () {
    $meeting = Meeting::factory()->create(['raw_transcript' => null]);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => true])
        ->assertDontSee('Summarize with AI');
});

it('does not show the summarize button without manage permission', function () {
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew.']);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => false])
        ->assertDontSee('Summarize with AI');
});

it('blocks summarize() without manage permission', function () {
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew.']);

    Livewire::test(MeetingSummary::class, ['meetingId' => $meeting->id, 'canManage' => false])
        ->call('summarize')
        ->assertForbidden();
});
