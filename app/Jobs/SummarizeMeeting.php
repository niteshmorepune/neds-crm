<?php

namespace App\Jobs;

use App\Enums\MeetingSummaryStatus;
use App\Models\Meeting;
use App\Services\AiAssistant;
use App\Support\Ai;
use App\Support\GoogleMeet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Summarizes a Meeting's stored raw_transcript via Claude (Phase 2 of Google
 * Meet Notes). Referenced by id, not a serialized model, so a deleted
 * meeting is a no-op. Mirrors TranscribeCallLogVoiceNote: flips status to
 * Processing before the call so the UI can poll, and every outcome is
 * absorbed into ai_summary_status rather than surfaced as a failed queue
 * job — an AI outage must never look like a system error.
 *
 * A Google-Meet-imported meeting requires GoogleMeet::summaryEnabled() (both
 * the integration flag AND AI); a manually-logged external meeting
 * (Zoom/Teams/etc., see MeetingImport::saveManualMeeting()) has nothing to
 * do with the Google integration, so it only needs Ai::enabled() — gating it
 * behind GOOGLE_MEET_ENABLED too would fail every manual summary for a shop
 * that has AI on but hasn't connected a Google account at all.
 */
class SummarizeMeeting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $meetingId) {}

    public function handle(AiAssistant $assistant): void
    {
        $meeting = Meeting::find($this->meetingId);

        if ($meeting === null || blank($meeting->raw_transcript)) {
            return;
        }

        $enabled = $meeting->isGoogleMeetImport() ? GoogleMeet::summaryEnabled() : Ai::enabled();

        if (! $enabled) {
            $meeting->forceFill(['ai_summary_status' => MeetingSummaryStatus::Failed])->saveQuietly();

            return;
        }

        $meeting->forceFill(['ai_summary_status' => MeetingSummaryStatus::Processing])->saveQuietly();

        $summary = $assistant->summarizeMeeting($meeting);

        if ($summary === null) {
            $meeting->forceFill(['ai_summary_status' => MeetingSummaryStatus::Failed])->saveQuietly();

            return;
        }

        $meeting->forceFill([
            'ai_summary' => $summary,
            'ai_summary_status' => MeetingSummaryStatus::Completed,
            'ai_summarized_at' => now(),
        ])->saveQuietly();
    }
}
