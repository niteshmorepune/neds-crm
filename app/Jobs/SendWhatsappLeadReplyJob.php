<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forwards a staff note on a Lead back to the contact via wadesk.in, when
 * the note was explicitly marked "send as WhatsApp reply" (opt-in — unlike
 * Ticket replies, Lead notes are internal-only by default).
 *
 * Fires only for notes on a Lead with sent_via_whatsapp=true and a set
 * whatsapp_conversation_id. Silently skips if wadesk config is absent.
 *
 * Failure is logged but never re-thrown — a wadesk.in outage must never
 * block the note-taking workflow.
 */
class SendWhatsappLeadReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $noteId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');

        if (! $baseUrl || ! $serviceKey) {
            return;
        }

        $note = Note::find($this->noteId);

        if ($note === null || ! $note->sent_via_whatsapp || ! ($note->notable instanceof Lead)) {
            return;
        }

        $lead = $note->notable;

        if (blank($lead->whatsapp_conversation_id)) {
            return;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send", [
                    'conversationId' => $lead->whatsapp_conversation_id,
                    'content' => $note->body,
                    'type' => 'text',
                ]);

            if (! $response->successful()) {
                Log::warning('SendWhatsappLeadReplyJob: wadesk.in returned non-2xx', [
                    'note_id' => $this->noteId,
                    'lead_id' => $lead->id,
                    'conversation_id' => $lead->whatsapp_conversation_id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendWhatsappLeadReplyJob: HTTP call to wadesk.in failed', [
                'note_id' => $this->noteId,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
