<?php

namespace App\Jobs;

use App\Enums\VoiceTranscriptStatus;
use App\Models\Attachment;
use App\Models\CallLog;
use App\Services\AnthropicClient;
use App\Services\GoogleSpeechClient;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Transcribes a Call Log's voice-note attachment (Hindi/Marathi/English) via
 * Google Speech-to-Text, then has Claude translate/clean the raw transcript
 * into natural English. Referenced by id, not a serialized model, so a
 * deleted call log or attachment is a no-op rather than a stale-data bug.
 *
 * AI failure at either step is swallowed (status becomes Failed) — this must
 * never break the core call-logging workflow.
 */
class TranscribeCallLogVoiceNote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $callLogId,
        public int $attachmentId,
    ) {}

    public function handle(GoogleSpeechClient $speech, AnthropicClient $anthropic): void
    {
        $call = CallLog::find($this->callLogId);
        $attachment = Attachment::find($this->attachmentId);

        if ($call === null || $attachment === null) {
            return;
        }

        if (! Ai::voiceTranscriptionEnabled()) {
            $call->forceFill(['voice_transcript_status' => VoiceTranscriptStatus::Failed])->saveQuietly();

            return;
        }

        $call->forceFill(['voice_transcript_status' => VoiceTranscriptStatus::Processing])->saveQuietly();

        $rawTranscript = $this->transcribe($speech, $attachment);

        if ($rawTranscript === null) {
            $call->forceFill(['voice_transcript_status' => VoiceTranscriptStatus::Failed])->saveQuietly();

            return;
        }

        $result = $anthropic->message(
            feature: 'call_voice_transcript_translate',
            prompt: "Voice-note transcript (may be Hindi, Marathi, English, or a mix):\n\n{$rawTranscript}",
            system: $this->system(),
            maxTokens: 1000,
        );

        if ($result === null || blank(trim($result->text))) {
            $call->forceFill(['voice_transcript_status' => VoiceTranscriptStatus::Failed])->saveQuietly();

            return;
        }

        $call->forceFill([
            'voice_transcript' => trim($result->text),
            'voice_transcript_status' => VoiceTranscriptStatus::Completed,
            'voice_transcribed_at' => now(),
        ])->saveQuietly();
    }

    private function transcribe(GoogleSpeechClient $speech, Attachment $attachment): ?string
    {
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            return null;
        }

        $bytes = Storage::disk($attachment->disk)->get($attachment->path);
        $encoding = str_contains($attachment->mime_type, 'ogg') ? 'OGG_OPUS' : 'WEBM_OPUS';

        return $speech->transcribe(base64_encode($bytes), $encoding);
    }

    private function system(): string
    {
        return <<<'PROMPT'
        You clean up voice-note transcripts for a digital-solutions agency's Call
        Log in India. The transcript may be Hindi, Marathi, English, or a mix
        (code-switching is common). Translate it into clear, natural English
        suitable as a sales/support call note, correcting obvious speech-to-text
        errors where the intent is clear. Keep client/company names, numbers, and
        dates exactly as given. Do not invent details that aren't in the
        transcript.

        Respond with ONLY the cleaned English note text — no preamble, no
        quotation marks, no markdown.
        PROMPT;
    }
}
