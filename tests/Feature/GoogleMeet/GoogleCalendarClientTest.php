<?php

use App\Models\GoogleAccountConnection;
use App\Services\GoogleCalendarClient;
use Illuminate\Support\Facades\Http;

it('lists only events that have a Meet link, filtering out plain calendar events', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
            'items' => [
                [
                    'id' => 'evt-with-meet',
                    'summary' => 'Client sync call',
                    'start' => ['dateTime' => '2026-07-20T10:00:00+05:30'],
                    'conferenceData' => ['conferenceId' => 'abc-defg-hij'],
                    'attendees' => [['displayName' => 'Priya Rep'], ['email' => 'client@example.com']],
                ],
                [
                    'id' => 'evt-no-meet',
                    'summary' => 'Just a reminder',
                    'start' => ['dateTime' => '2026-07-21T09:00:00+05:30'],
                ],
            ],
        ]),
    ]);

    $events = app(GoogleCalendarClient::class)->listRecentMeetEvents($connection);

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe('evt-with-meet')
        ->and($events[0]['title'])->toBe('Client sync call')
        ->and($events[0]['attendees'])->toBe(['Priya Rep', 'client@example.com']);
});

it('returns null when the connection cannot be refreshed', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->subMinute()]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response('error', 401)]);

    expect(app(GoogleCalendarClient::class)->listRecentMeetEvents($connection))->toBeNull();
});

it('returns null when the events call itself fails', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response('error', 500)]);

    expect(app(GoogleCalendarClient::class)->listRecentMeetEvents($connection))->toBeNull();
});
