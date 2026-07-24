<?php

use App\Models\GoogleAccountConnection;
use App\Services\GoogleMeetImportClient;
use Illuminate\Support\Facades\Http;

it('fetches event detail, matches the recording + transcript attachments, and exports the transcript text', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
            'end' => ['dateTime' => '2026-07-20T10:30:00+05:30'],
            'attendees' => [['displayName' => 'Priya Rep']],
            'attachments' => [
                ['mimeType' => 'video/mp4', 'fileUrl' => 'https://drive.google.com/recording'],
                ['mimeType' => 'application/vnd.google-apps.document', 'fileUrl' => 'https://docs.google.com/transcript', 'fileId' => 'doc-123'],
            ],
        ]),
        'www.googleapis.com/drive/v3/files/doc-123/export*' => Http::response('Rep: hello, how are you?'),
    ]);

    $detail = app(GoogleMeetImportClient::class)->fetchEventDetail($connection, 'evt-1');

    expect($detail)->not->toBeNull()
        ->and($detail['title'])->toBe('Client sync call')
        ->and($detail['duration_minutes'])->toBe(30)
        ->and($detail['attendees'])->toBe(['Priya Rep'])
        ->and($detail['drive_recording_url'])->toBe('https://drive.google.com/recording')
        ->and($detail['drive_transcript_url'])->toBe('https://docs.google.com/transcript')
        ->and($detail['raw_transcript'])->toBe('Rep: hello, how are you?');
});

it('still returns event detail with a null transcript when no transcript attachment exists yet', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events/evt-1' => Http::response([
            'summary' => 'Client sync call',
            'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
        ]),
    ]);

    $detail = app(GoogleMeetImportClient::class)->fetchEventDetail($connection, 'evt-1');

    expect($detail)->not->toBeNull()
        ->and($detail['raw_transcript'])->toBeNull()
        ->and($detail['drive_recording_url'])->toBeNull()
        ->and($detail['duration_minutes'])->toBeNull();
});

it('returns null when the event fetch fails', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response('not found', 404)]);

    expect(app(GoogleMeetImportClient::class)->fetchEventDetail($connection, 'evt-1'))->toBeNull();
});
