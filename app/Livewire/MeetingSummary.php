<?php

namespace App\Livewire;

use App\Enums\MeetingSummaryStatus;
use App\Jobs\SummarizeMeeting;
use App\Models\Meeting;
use App\Support\GoogleMeet;
use Livewire\Component;

/**
 * Polls a Meeting's ai_summary_status while the background SummarizeMeeting
 * job runs, then settles once finished (mirrors CallVoiceTranscript). Also
 * offers a manual summarize/retry trigger for meetings imported before a
 * summary was attempted, or whose summary failed. No dedicated Policy: only
 * ever rendered inside MeetingImport's already policy-gated Customer/Lead
 * show page — but summarize() still checks $canManage defensively.
 */
class MeetingSummary extends Component
{
    public int $meetingId;

    public bool $canManage = false;

    public ?string $status = null;

    public ?string $summary = null;

    public bool $hasTranscript = false;

    public function mount(int $meetingId, bool $canManage = false): void
    {
        $this->meetingId = $meetingId;
        $this->canManage = $canManage;
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $meeting = Meeting::find($this->meetingId);

        $this->status = $meeting?->ai_summary_status?->value;
        $this->summary = $meeting?->ai_summary;
        $this->hasTranscript = filled($meeting?->raw_transcript);
    }

    public function summarize(): void
    {
        abort_unless($this->canManage, 403);

        $meeting = Meeting::find($this->meetingId);

        if ($meeting === null || blank($meeting->raw_transcript)) {
            return;
        }

        $meeting->forceFill(['ai_summary_status' => MeetingSummaryStatus::Pending])->saveQuietly();
        SummarizeMeeting::dispatch($meeting->id);

        $this->refreshStatus();
    }

    public function render()
    {
        return view('livewire.meeting-summary', [
            'summaryFeatureEnabled' => GoogleMeet::summaryEnabled(),
        ]);
    }
}
