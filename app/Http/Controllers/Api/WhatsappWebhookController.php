<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'           => ['required', 'string'],
            'contact_name'    => ['nullable', 'string', 'max:255'],
            'message'         => ['nullable', 'string'],
            'conversation_id' => ['required', 'string'],
        ]);

        // Dedup: one CRM ticket per wadesk.in conversation.
        if (Ticket::where('whatsapp_conversation_id', $data['conversation_id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $customer = $this->findCustomer($data['phone']);

        if (! $customer) {
            return response()->json(['status' => 'no_customer_match']);
        }

        $preview = trim($data['message'] ?? '');
        $subject = 'WhatsApp: ' . str($preview)->limit(80, '…');

        Ticket::create([
            'customer_id'              => $customer->id,
            'subject'                  => $subject ?: 'WhatsApp enquiry',
            'description'              => $preview ?: '(media or non-text message)',
            'priority'                 => TicketPriority::Normal->value,
            'status'                   => TicketStatus::Open->value,
            'channel'                  => 'whatsapp',
            'whatsapp_conversation_id' => $data['conversation_id'],
            'sla_due_at'               => now()->addHours(4),
        ]);

        return response()->json(['status' => 'created']);
    }

    private function findCustomer(string $rawPhone): ?Customer
    {
        // Normalize: digits only (wadesk.in stores without +, e.g. 919028099919)
        $digits = preg_replace('/\D/', '', $rawPhone);
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return Customer::where('phone', $digits)->first()
            ?? Customer::where('phone', '+' . $digits)->first()
            ?? Customer::where('phone', 'LIKE', '%' . $last10)->first();
    }
}
