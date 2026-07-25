<?php

namespace App\Services;

use App\Models\GoogleAccountConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reads the company connection's recent Calendar events (the picker source
 * for "Import Meet Notes") and creates new Meet-enabled events on its
 * behalf ("Create Meeting", added 2026-07-25, used by any staff member
 * scheduling a call with a client or lead). Plain REST via Laravel's HTTP
 * client, no SDK.
 */
class GoogleCalendarClient
{
    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    /**
     * @return list<array{id: string, title: string, start: Carbon, attendees: list<string>}>|null
     *                                                                                             Null (never an exception) if the token can't be refreshed or the call fails.
     */
    public function listRecentMeetEvents(GoogleAccountConnection $connection, int $days = 14): ?array
    {
        if (! $this->oauth->ensureFreshToken($connection)) {
            return null;
        }

        try {
            $response = Http::withToken($connection->access_token)
                ->timeout(15)
                ->get(self::EVENTS_URL, [
                    'timeMin' => now()->subDays($days)->toRfc3339String(),
                    'timeMax' => now()->toRfc3339String(),
                    'singleEvents' => 'true',
                    'orderBy' => 'startTime',
                    'maxResults' => 50,
                ]);

            if (! $response->successful()) {
                Log::warning('Google Calendar events fetch failed', ['user_id' => $connection->user_id, 'status' => $response->status()]);

                return null;
            }

            $items = $response->json('items') ?? [];

            return collect($items)
                // Only events with an actual Meet link — everything else on
                // the calendar is irrelevant to this feature.
                ->filter(fn (array $event) => filled($event['conferenceData']['conferenceId'] ?? null))
                ->map(fn (array $event) => [
                    'id' => $event['id'],
                    'title' => $event['summary'] ?? '(No title)',
                    'start' => Carbon::parse($event['start']['dateTime'] ?? $event['start']['date']),
                    'attendees' => collect($event['attendees'] ?? [])
                        ->map(fn (array $a) => $a['displayName'] ?? $a['email'] ?? null)
                        ->filter()
                        ->values()
                        ->all(),
                ])
                ->sortByDesc('start')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar events exception', ['user_id' => $connection->user_id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Creates a Calendar event with an auto-generated Google Meet link and,
     * if given, invites one attendee — `sendUpdates=all` so Google emails
     * them the invite directly. Used by "Create Meeting" so any staff
     * member can schedule a client/lead call through the company connection
     * without needing their own Google account.
     *
     * @return array{event_id: string, meet_link: string|null}|null
     */
    public function createMeetingEvent(GoogleAccountConnection $connection, string $title, ?string $attendeeEmail, Carbon $start, int $durationMinutes = 30): ?array
    {
        if (! $this->oauth->ensureFreshToken($connection)) {
            return null;
        }

        try {
            $response = Http::withToken($connection->access_token)
                ->timeout(15)
                ->post(self::EVENTS_URL.'?conferenceDataVersion=1&sendUpdates=all', [
                    'summary' => $title,
                    'start' => ['dateTime' => $start->toRfc3339String()],
                    'end' => ['dateTime' => $start->copy()->addMinutes($durationMinutes)->toRfc3339String()],
                    'attendees' => $attendeeEmail ? [['email' => $attendeeEmail]] : [],
                    'conferenceData' => [
                        'createRequest' => [
                            'requestId' => (string) Str::uuid(),
                            'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Google Calendar create-event failed', ['user_id' => $connection->user_id, 'status' => $response->status()]);

                return null;
            }

            $event = $response->json();

            return [
                'event_id' => $event['id'],
                'meet_link' => $event['hangoutLink'] ?? ($event['conferenceData']['entryPoints'][0]['uri'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning('Google Calendar create-event exception', ['user_id' => $connection->user_id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
