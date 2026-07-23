<?php

namespace App\Livewire;

use App\Models\CallLog;
use Livewire\Component;

/**
 * Polls a Call Log's voice-transcript status while the background
 * TranscribeCallLogVoiceNote job is running, then settles once finished.
 * No dedicated Policy — only ever rendered inside the Calling index table,
 * which already scopes rows to the viewing user (or all, for managers).
 */
class CallVoiceTranscript extends Component
{
    public int $callLogId;

    public ?string $status = null;

    public ?string $transcript = null;

    public ?string $audioUrl = null;

    public function mount(int $callLogId): void
    {
        $this->callLogId = $callLogId;
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $call = CallLog::find($this->callLogId);

        $this->status = $call?->voice_transcript_status?->value;
        $this->transcript = $call?->voice_transcript;

        // Shown regardless of transcript status — the raw recording is always
        // playable even if AI transcription is still pending or failed, so a
        // voice note is never inaccessible just because translation didn't work.
        $attachment = $call?->attachments()->latest()->first();
        $this->audioUrl = $attachment ? route('attachments.download', $attachment) : null;
    }

    public function render()
    {
        return view('livewire.call-voice-transcript');
    }
}
