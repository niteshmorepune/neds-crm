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

it('createMeetingEvent creates a Meet-enabled event, invites the given attendee, and asks Google to notify them', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'new-evt-1',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
        ]),
    ]);

    $result = app(GoogleCalendarClient::class)->createMeetingEvent(
        $connection, 'NEDS <> Client', ['client@example.com'], now()->addHour()
    );

    expect($result)->toBe(['event_id' => 'new-evt-1', 'meet_link' => 'https://meet.google.com/abc-defg-hij']);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_contains($request->url(), 'conferenceDataVersion=1')
            && str_contains($request->url(), 'sendUpdates=all')
            && $body['summary'] === 'NEDS <> Client'
            && ($body['attendees'][0]['email'] ?? null) === 'client@example.com'
            && ($body['conferenceData']['createRequest']['conferenceSolutionKey']['type'] ?? null) === 'hangoutsMeet';
    });
});

it('createMeetingEvent invites multiple attendees at once, deduplicated', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'new-evt-multi',
            'hangoutLink' => 'https://meet.google.com/multi',
        ]),
    ]);

    app(GoogleCalendarClient::class)->createMeetingEvent(
        $connection, 'NEDS <> Client', ['client@example.com', 'mohit@neds.test', 'client@example.com'], now()->addHour()
    );

    Http::assertSent(function ($request) {
        $emails = collect($request->data()['attendees'])->pluck('email')->all();

        return $emails === ['client@example.com', 'mohit@neds.test'];
    });
});

it('createMeetingEvent falls back to the conferenceData entry point when hangoutLink is absent', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake([
        'www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response([
            'id' => 'new-evt-2',
            'conferenceData' => ['entryPoints' => [['uri' => 'https://meet.google.com/fallback-link']]],
        ]),
    ]);

    $result = app(GoogleCalendarClient::class)->createMeetingEvent(
        $connection, 'NEDS <> Client', [], now()->addHour()
    );

    expect($result)->toBe(['event_id' => 'new-evt-2', 'meet_link' => 'https://meet.google.com/fallback-link']);
});

it('createMeetingEvent returns null when the API call fails', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->addHour()]);
    Http::fake(['www.googleapis.com/calendar/v3/calendars/primary/events?*' => Http::response('error', 500)]);

    expect(app(GoogleCalendarClient::class)->createMeetingEvent(
        $connection, 'NEDS <> Client', ['client@example.com'], now()->addHour()
    ))->toBeNull();
});

it('createMeetingEvent returns null when the connection cannot be refreshed', function () {
    $connection = GoogleAccountConnection::factory()->create(['expires_at' => now()->subMinute()]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response('error', 401)]);

    expect(app(GoogleCalendarClient::class)->createMeetingEvent(
        $connection, 'NEDS <> Client', ['client@example.com'], now()->addHour()
    ))->toBeNull();
});
