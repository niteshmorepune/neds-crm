<?php

namespace App\Services;

use App\Models\GoogleAccountConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fetches a single Calendar event's full detail — including the recording/
 * transcript Google Meet itself already attaches once processing finishes —
 * and the transcript document's plain-text content via Drive's export
 * endpoint.
 *
 * UNVERIFIED AGAINST A REAL LIVE EVENT as of Phase 1 build time (no recorded
 * meeting has happened yet on this fresh Workspace). The attachment
 * mime-type matching below follows Google's documented behavior (recording
 * = video/mp4, transcript = a Google Doc attached to the same Calendar
 * event once ready) — if a real import comes back with an empty transcript
 * despite Meet having definitely finished processing one, check this
 * matching logic first, live, against a real event's raw `attachments`
 * payload before assuming a deeper bug.
 */
class GoogleMeetImportClient
{
    private const CALENDAR_EVENT_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/';

    private const DRIVE_EXPORT_URL = 'https://www.googleapis.com/drive/v3/files/';

    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    /**
     * @return array{title: string, occurred_at: Carbon, duration_minutes: int|null, attendees: list<string>, drive_recording_url: string|null, drive_transcript_url: string|null, raw_transcript: string|null}|null
     */
    public function fetchEventDetail(GoogleAccountConnection $connection, string $eventId): ?array
    {
        if (! $this->oauth->ensureFreshToken($connection)) {
            return null;
        }

        try {
            $response = Http::withToken($connection->access_token)
                ->timeout(15)
                ->get(self::CALENDAR_EVENT_URL.$eventId);

            if (! $response->successful()) {
                Log::warning('Google Meet event detail fetch failed', ['user_id' => $connection->user_id, 'status' => $response->status()]);

                return null;
            }

            $event = $response->json();

            $start = Carbon::parse($event['start']['dateTime'] ?? $event['start']['date']);
            $end = isset($event['end']['dateTime']) ? Carbon::parse($event['end']['dateTime']) : null;

            $attachments = collect($event['attachments'] ?? []);
            $recording = $attachments->first(fn (array $a) => Str::startsWith($a['mimeType'] ?? '', 'video/'));
            $transcriptDoc = $attachments->first(fn (array $a) => ($a['mimeType'] ?? null) === 'application/vnd.google-apps.document');

            return [
                'title' => $event['summary'] ?? '(No title)',
                'occurred_at' => $start,
                'duration_minutes' => $end ? (int) round($start->diffInSeconds($end) / 60) : null,
                'attendees' => collect($event['attendees'] ?? [])
                    ->map(fn (array $a) => $a['displayName'] ?? $a['email'] ?? null)
                    ->filter()
                    ->values()
                    ->all(),
                'drive_recording_url' => $recording['fileUrl'] ?? null,
                'drive_transcript_url' => $transcriptDoc['fileUrl'] ?? null,
                'raw_transcript' => $transcriptDoc
                    ? $this->exportTranscriptText($connection, $transcriptDoc['fileId'])
                    : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Google Meet event detail exception', ['user_id' => $connection->user_id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Exports a Google Doc's content as plain text. Null (not an exception) if the doc isn't ready or the export fails. */
    private function exportTranscriptText(GoogleAccountConnection $connection, string $fileId): ?string
    {
        try {
            $response = Http::withToken($connection->access_token)
                ->timeout(15)
                ->get(self::DRIVE_EXPORT_URL.$fileId.'/export', ['mimeType' => 'text/plain']);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            Log::warning('Google Drive transcript export exception', ['user_id' => $connection->user_id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
