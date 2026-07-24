<?php

namespace App\Jobs;

use App\Enums\MeetingSummaryStatus;
use App\Models\Meeting;
use App\Services\AiAssistant;
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

        if (! GoogleMeet::summaryEnabled()) {
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
