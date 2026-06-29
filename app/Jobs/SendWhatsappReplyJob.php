<?php

namespace App\Jobs;

use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forwards a staff reply on a WhatsApp ticket back to the customer via
 * wadesk.in (Tier 3 integration).
 *
 * Fires only for non-internal staff replies on tickets where channel='whatsapp'
 * and whatsapp_conversation_id is set. Silently skips if wadesk config is absent.
 *
 * Failure is logged but never re-thrown — a wadesk.in outage must never block
 * the ticket reply workflow.
 */
class SendWhatsappReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $replyId) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        $serviceKey = (string) config('services.wadesk.service_key');

        if (! $baseUrl || ! $serviceKey) {
            return;
        }

        $reply = TicketReply::with('ticket')->find($this->replyId);

        if ($reply === null) {
            return;
        }

        $ticket = $reply->ticket;

        // Guard: only forward staff (non-customer) non-internal replies on WhatsApp tickets.
        if ($reply->is_internal
            || $reply->isFromCustomer()
            || ($ticket->channel ?? '') !== 'whatsapp'
            || blank($ticket->whatsapp_conversation_id)) {
            return;
        }

        try {
            $response = Http::withHeaders(['X-Service-Key' => $serviceKey])
                ->timeout(15)
                ->post("{$baseUrl}/api/send", [
                    'conversationId' => $ticket->whatsapp_conversation_id,
                    'content' => $reply->body,
                    'type' => 'text',
                ]);

            if (! $response->successful()) {
                Log::warning('SendWhatsappReplyJob: wadesk.in returned non-2xx', [
                    'reply_id' => $this->replyId,
                    'ticket_id' => $ticket->id,
                    'conversation_id' => $ticket->whatsapp_conversation_id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SendWhatsappReplyJob: HTTP call to wadesk.in failed', [
                'reply_id' => $this->replyId,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
