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
    }

    public function render()
    {
        return view('livewire.call-voice-transcript');
    }
}
