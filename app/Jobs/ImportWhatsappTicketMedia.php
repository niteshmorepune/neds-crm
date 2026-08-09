<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Fetches the actual media file for a WhatsApp-originated ticket's opening
 * message (image/document/audio/video) and stores it as a real Attachment,
 * so the assigned team member sees the file itself instead of the
 * "[image]"/"[document]" placeholder text wadesk.in uses as content for a
 * caption-less media message.
 *
 * wadesk.in only ever stores Meta's media ID, not a downloadable URL or the
 * raw bytes — its own media proxy (`GET /api/media/{id}?conversationId=`)
 * resolves that ID to Meta's short-lived download URL and streams the file.
 * That route normally requires a staff NextAuth session; it also accepts the
 * same X-Service-Key shared secret already used for every other CRM<->wadesk
 * server-to-server call (see WADESK_SERVICE_KEY in services.php).
 *
 * Fetched eagerly at ticket-creation time and stored locally (matching how
 * every other attachment in this app is stored), not proxied live on every
 * ticket view — so the attachment keeps working even after Meta's media ID
 * eventually expires.
 */
class ImportWhatsappTicketMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $ticketId,
        public string $conversationId,
        public string $mediaId,
        public string $mediaType,
    ) {}

    public function handle(): void
    {
        $ticket = Ticket::find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $serviceKey = (string) config('services.wadesk.service_key');

        if ($serviceKey === '') {
            Log::warning('WhatsApp ticket media import skipped: no wadesk service key configured', [
                'ticket_id' => $this->ticketId,
            ]);

            return;
        }

        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');

        try {
            $response = Http::timeout(20)
                ->withHeaders(['X-Service-Key' => $serviceKey])
                ->get("{$baseUrl}/api/media/{$this->mediaId}", ['conversationId' => $this->conversationId]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp ticket media fetch exception', [
                'ticket_id' => $this->ticketId,
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $response->successful()) {
            Log::warning('WhatsApp ticket media fetch failed', [
                'ticket_id' => $this->ticketId,
                'media_id' => $this->mediaId,
                'status' => $response->status(),
            ]);

            return;
        }

        $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin';
        $path = 'attachments/'.Str::uuid().'.'.$extension;
        $body = $response->body();

        Storage::disk('local')->put($path, $body);

        $ticket->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => "whatsapp-{$this->mediaType}-{$ticket->id}.{$extension}",
            'mime_type' => $mimeType,
            'size' => strlen($body),
        ]);
    }
}
