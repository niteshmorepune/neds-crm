<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Ticket;
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
        ]);

        // Dedup: one CRM ticket per wadesk.in conversation.
        if (Ticket::where('whatsapp_conversation_id', $data['conversation_id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $customer = $this->findCustomer($data['phone']);

        if (! $customer) {
            return $this->handleUnmatchedNumber($data);
        }

        $preview = trim($data['message'] ?? '');
        $subject = 'WhatsApp: '.str($preview)->limit(80, '…');

        $description = $preview ?: '(media or non-text message)';

        if ($customer->drishti_client_id) {
            $base = rtrim((string) config('services.drishti.base_url'), '/');
            $description .= "\n\n— Drishti context: {$base}/clients/{$customer->drishti_client_id}";
        }

        Ticket::create([
            'customer_id' => $customer->id,
            'subject' => $subject ?: 'WhatsApp enquiry',
            'description' => $description,
            'priority' => TicketPriority::Normal->value,
            'status' => TicketStatus::Open->value,
            'channel' => 'whatsapp',
            'whatsapp_conversation_id' => $data['conversation_id'],
            'sla_due_at' => now()->addHours(4),
        ]);

        return response()->json(['status' => 'created']);
    }

    /**
     * No CRM customer matches this phone number — capture the inquiry as a
     * Lead instead of dropping it. Deduped by conversation_id (mirrors the
     * Ticket dedup above): the first message in a new conversation creates
     * the lead, later messages in the same conversation just add a note.
     */
    private function handleUnmatchedNumber(array $data): JsonResponse
    {
        $lead = Lead::where('whatsapp_conversation_id', $data['conversation_id'])->first();

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
        // Normalize: digits only (wadesk.in stores without +, e.g. 919028099919)
        $digits = preg_replace('/\D/', '', $rawPhone);
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return Customer::where('phone', $digits)->first()
            ?? Customer::where('phone', '+'.$digits)->first()
            ?? Customer::where('phone', 'LIKE', '%'.$last10)->first();
    }
}
