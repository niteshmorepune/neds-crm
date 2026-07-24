<?php

namespace App\Services;

use App\Models\GoogleAccountConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lists a connected user's own recent Calendar events that have a Google
 * Meet link — the picker source for "Import Meet Notes." Plain REST via
 * Laravel's HTTP client, no SDK.
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
}
