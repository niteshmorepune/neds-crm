<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort companion to App\Actions\FlagPossibleDuplicateLead: tells
 * wadesk.in to set Conversation.aiMuted = true on the flagged Lead's
 * conversation, the same "stop automation, a human owns this now" flag
 * already set when a staff member sends a manual reply from wadesk.in's
 * own inbox (see that app's api/send/route.ts). Reuses the existing
 * X-Service-Key server-to-server auth (WADESK_SERVICE_KEY) — same trust
 * boundary as SendWhatsappLeadReplyJob's call to POST /api/send, just a
 * different, dedicated endpoint (POST /api/conversations/mute).
 *
 * Deliberately does not, and cannot, undo the auto-reply that already went
 * out before FlagPossibleDuplicateLead ran — see DuplicateLeadDetector's
 * own docblock on why this is only ever an after-the-fact alert. This only
 * stops the NEXT automated reply on the same conversation. A failure here
 * (wadesk.in down, endpoint missing on an older deploy, no
 * whatsapp_conversation_id) is silently logged, never retried aggressively
 * and never surfaced to the user — staff were already notified by
 * PossibleDuplicateLeadNotification regardless of whether this succeeds.
 */
class MuteWadeskConversationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public string $conversationId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');

        if (! $baseUrl || ! $serviceKey || blank($this->conversationId)) {
            return;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(10)
                ->post("{$baseUrl}/api/conversations/mute", [
                    'conversationId' => $this->conversationId,
                ]);

            if (! $response->successful()) {
                Log::warning('MuteWadeskConversationJob: wadesk.in returned non-2xx', [
                    'conversation_id' => $this->conversationId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('MuteWadeskConversationJob: HTTP call to wadesk.in failed', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
