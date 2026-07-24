<?php

use App\Enums\MeetingSummaryStatus;
use App\Jobs\SummarizeMeeting;
use App\Models\AiUsage;
use App\Models\Meeting;
use App\Services\AiAssistant;
use Illuminate\Support\Facades\Http;

function enableGoogleMeetSummaryAi(): void
{
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
        'services.anthropic.enabled' => true,
        'services.anthropic.key' => 'sk-test',
    ]);
}

it('summarizes a meeting transcript and marks it completed', function () {
    enableGoogleMeetSummaryAi();
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "Key points:\n- Discussed renewal"]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 40],
        ]),
    ]);
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew the AMC.']);

    (new SummarizeMeeting($meeting->id))->handle(app(AiAssistant::class));

    $meeting->refresh();
    expect($meeting->ai_summary_status)->toBe(MeetingSummaryStatus::Completed)
        ->and($meeting->ai_summary)->toContain('Discussed renewal')
        ->and($meeting->ai_summarized_at)->not->toBeNull();

    expect(AiUsage::where('feature', 'summarize_meeting')->first())
        ->input_tokens->toBe(80)
        ->output_tokens->toBe(40);
});

it('marks the meeting failed when Claude fails', function () {
    enableGoogleMeetSummaryAi();
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew the AMC.']);

    (new SummarizeMeeting($meeting->id))->handle(app(AiAssistant::class));

    expect($meeting->refresh()->ai_summary_status)->toBe(MeetingSummaryStatus::Failed);
});

it('marks the meeting failed when AI is disabled', function () {
    config([
        'services.google_meet.enabled' => true,
        'services.google_meet.client_id' => 'test-client-id',
        'services.google_meet.client_secret' => 'test-client-secret',
        'services.anthropic.enabled' => false,
    ]);
    Http::fake();
    $meeting = Meeting::factory()->create(['raw_transcript' => 'Client: lets renew the AMC.']);

    (new SummarizeMeeting($meeting->id))->handle(app(AiAssistant::class));

    expect($meeting->refresh()->ai_summary_status)->toBe(MeetingSummaryStatus::Failed);
    Http::assertNothingSent();
});

it('is a no-op when there is no transcript to summarize', function () {
    enableGoogleMeetSummaryAi();
    Http::fake();
    $meeting = Meeting::factory()->create(['raw_transcript' => null]);

    (new SummarizeMeeting($meeting->id))->handle(app(AiAssistant::class));

    expect($meeting->refresh()->ai_summary_status)->toBeNull();
    Http::assertNothingSent();
});

it('is a no-op when the meeting no longer exists', function () {
    enableGoogleMeetSummaryAi();
    Http::fake();

    (new SummarizeMeeting(99999))->handle(app(AiAssistant::class));

    Http::assertNothingSent();
});
