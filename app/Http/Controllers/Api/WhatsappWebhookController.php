<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportWhatsappTicketMedia;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Ticket;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'conversation_id' => ['required', 'string'],
            'whatsapp_number' => ['nullable', 'string'],
            'whatsapp_line_label' => ['nullable', 'string'],
            // Meta media ID for an image/document/audio/video message —
            // wadesk.in only ever sends the ID, never a downloadable URL.
            'media_id' => ['nullable', 'string'],
            'media_type' => ['nullable', 'string'],
        ]);

        // Dedup: one CRM ticket per wadesk.in conversation.
        if (Ticket::where('whatsapp_conversation_id', $data['conversation_id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        // Any line other than the configured support number is always
        // pre-sale/lead activity — never a Ticket, even from a known
        // Customer's phone (owner-confirmed 2026-08-03). A missing
        // whatsapp_number (older wadesk.in build, or a not-yet-configured
        // line) defaults to the support-line behavior for backward compatibility.
        $supportNumber = config('services.wadesk.support_number');
        $isSupportLine = blank($data['whatsapp_number'] ?? null) || $data['whatsapp_number'] === $supportNumber;

        if (! $isSupportLine) {
            return $this->handleUnmatchedNumber($data);
        }

        $customer = $this->findCustomer($data['phone']);

        if (! $customer) {
            return $this->handleUnmatchedNumber($data);
        }

        $preview = trim($data['message'] ?? '');
        $mediaType = $data['media_type'] ?? null;

        // A caption-less media message: wadesk.in's own content fallback is
        // the literal string "[image]"/"[document]"/etc — surfacing that
        // verbatim as the ticket subject/description reads as a broken
        // placeholder rather than a real message. Give it a plain-English
        // label instead; the actual file (once ImportWhatsappTicketMedia
        // finishes) appears in Attachments below.
        $isGenericMediaPlaceholder = $mediaType && $preview === "[{$mediaType}]";
        $label = $isGenericMediaPlaceholder ? ucfirst($mediaType).' received' : $preview;

        $subject = 'WhatsApp: '.str($label)->limit(80, '…');

        $description = match (true) {
            $isGenericMediaPlaceholder => ucfirst($mediaType).' received — see Attachments below.',
            $preview !== '' => $preview,
            default => '(media or non-text message)',
        };

        if ($customer->drishti_client_id) {
            $base = rtrim((string) config('services.drishti.base_url'), '/');
            $description .= "\n\n— Drishti context: {$base}/clients/{$customer->drishti_client_id}";
        }

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'subject' => $subject ?: 'WhatsApp enquiry',
            'description' => $description,
            'priority' => TicketPriority::Normal->value,
            'status' => TicketStatus::Open->value,
            'channel' => 'whatsapp',
            'whatsapp_conversation_id' => $data['conversation_id'],
            'sla_due_at' => now()->addHours(4),
        ]);

        if (! empty($data['media_id'])) {
            ImportWhatsappTicketMedia::dispatch(
                $ticket->id,
                $data['conversation_id'],
                $data['media_id'],
                $mediaType ?? 'file',
            );
        }

        return response()->json(['status' => 'created']);
    }

    /**
     * No CRM customer matches this phone number — capture the inquiry as a
     * Lead instead of dropping it. Deduped by conversation_id (mirrors the
     * Ticket dedup above): the first message in a new conversation creates
     * the lead, later messages in the same conversation just add a note.
     *
     * Also checked against any OPEN lead with this phone from a DIFFERENT
     * channel (Lead::findOpenByPhone) — Meta's Lead Ad flow automatically
     * sends a WhatsApp message on the submitter's behalf right after they
     * submit the Instant Form, which otherwise lands as a second, separate
     * lead a few seconds after ImportMetaLead's Meta Ads lead (a real
     * duplicate-lead pattern found in production 2026-08-13).
     */
    private function handleUnmatchedNumber(array $data): JsonResponse
    {
        $lead = Lead::where('whatsapp_conversation_id', $data['conversation_id'])->first();

        if ($lead === null) {
            $lead = Lead::findOpenByPhone($data['phone']);

            // Backfill so the NEXT message in this conversation hits the
            // fast conversation_id check above instead of re-scanning by
            // phone every time. Only when still unset — a lead already tied
            // to a different conversation keeps that one untouched (unique
            // column; this is presumably a second, separate WhatsApp thread).
            if ($lead !== null && $lead->whatsapp_conversation_id === null) {
                $lead->update(['whatsapp_conversation_id' => $data['conversation_id']]);
            }
        }

        if ($lead) {
            if (filled($data['message'] ?? null)) {
                $lead->notes()->create(['user_id' => null, 'body' => $data['message']]);
            }

            return response()->json(['status' => 'lead_note_added', 'lead_id' => $lead->id]);
        }

        $lead = Lead::create([
            'name' => ($data['contact_name'] ?? null) ?: 'WhatsApp Inquiry',
            'phone' => $data['phone'],
            'source' => LeadSource::Whatsapp->value,
            'status' => LeadStatus::New->value,
            'owner_id' => null,
            'whatsapp_conversation_id' => $data['conversation_id'],
        ]);

        if (filled($data['message'] ?? null)) {
            $lead->notes()->create(['user_id' => null, 'body' => $data['message']]);
        }

        return response()->json(['status' => 'lead_created', 'lead_id' => $lead->id]);
    }

    private function findCustomer(string $rawPhone): ?Customer
    {
        $digits = Phone::digits($rawPhone);

        return Customer::where('phone', $digits)->first()
            ?? Customer::where('phone', '+'.$digits)->first()
            ?? Customer::where('phone', 'LIKE', '%'.Phone::last10($rawPhone))->first();
    }
}
