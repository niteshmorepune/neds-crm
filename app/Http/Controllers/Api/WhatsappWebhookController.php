<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Http\Controllers\Controller;
use App\Jobs\ImportWhatsappTicketMedia;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\VisibilityAuditTouch;
use App\Models\WadeskMessageLog;
use App\Services\VisibilityAuditFunnelMetrics;
use App\Support\Phone;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappWebhookController extends Controller
{
    public function __construct(private readonly VisibilityAuditFunnelMetrics $vaMetrics) {}

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
            // The four fields below are only sent by a wadesk.in build that
            // notifies the CRM on EVERY message, not just a new/reopened
            // conversation's opening one (see [[project-progress]]/
            // [[backlog]]) — all optional, defaulting to the shape an older
            // wadesk.in build already sends, so this endpoint stays backward
            // compatible across an out-of-order deploy of the two apps.
            'message_id' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:inbound,outbound'],
            'sender_type' => ['nullable', 'string', 'in:customer,agent,ai,crm'],
            'sender_name' => ['nullable', 'string', 'max:255'],
        ]);

        $direction = $data['direction'] ?? 'inbound';
        $senderType = $data['sender_type'] ?? 'customer';

        // wadesk.in echoing back a message the CRM itself just sent (a staff
        // reply via SendWhatsappReplyJob/SendWhatsappLeadReplyJob, or the
        // Deal-Won/Visibility-Audit template jobs) — already recorded here at
        // send time, so there is nothing new to do with it.
        if ($senderType === 'crm') {
            return response()->json(['status' => 'ignored', 'reason' => 'own_send']);
        }

        // A build that tags every call with a real message_id lets us dedupe
        // a retried webhook delivery precisely, at the individual-message
        // level. An older build (or a legacy call with no message_id) falls
        // back to the pre-existing, coarser conversation-level dedup below.
        $messageId = $data['message_id'] ?? null;

        if ($messageId !== null) {
            try {
                WadeskMessageLog::create(['wadesk_message_id' => $messageId]);
            } catch (QueryException $e) {
                return response()->json(['status' => 'duplicate']);
            }
        }

        $existingTicket = Ticket::where('whatsapp_conversation_id', $data['conversation_id'])->first();

        if ($existingTicket) {
            // No message_id: an older wadesk.in build, which never calls this
            // webhook a second time for an already-open conversation — so a
            // repeat call here is a retried delivery of the SAME message,
            // not a genuinely new one. Preserve the original behavior exactly.
            if ($messageId === null) {
                return response()->json(['status' => 'duplicate']);
            }

            return $this->appendTicketReply($existingTicket, $data, $direction, $senderType);
        }

        // Any line other than the configured support number is always
        // pre-sale/lead activity — never a Ticket, even from a known
        // Customer's phone (owner-confirmed 2026-08-03). A missing
        // whatsapp_number (older wadesk.in build, or a not-yet-configured
        // line) defaults to the support-line behavior for backward compatibility.
        $supportNumber = config('services.wadesk.support_number');
        $isSupportLine = blank($data['whatsapp_number'] ?? null) || $data['whatsapp_number'] === $supportNumber;

        if (! $isSupportLine) {
            return $this->handleUnmatchedNumber($data, $direction, $senderType);
        }

        $customer = $this->findCustomer($data['phone']);

        if (! $customer) {
            return $this->handleUnmatchedNumber($data, $direction, $senderType);
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
     * The conversation already has a Ticket — add this message as a
     * TicketReply instead of creating a second ticket (only reachable when
     * wadesk.in sent a message_id; see handle()). Covers both directions: a
     * later message from the actual customer, and a reply a staffer or the
     * AI after-hours assistant sent directly from wadesk.in's own UI (as
     * opposed to from the CRM, which is filtered out before this is ever
     * reached).
     */
    private function appendTicketReply(Ticket $ticket, array $data, string $direction, string $senderType): JsonResponse
    {
        $message = trim($data['message'] ?? '');
        $mediaType = $data['media_type'] ?? null;
        $isGenericMediaPlaceholder = $mediaType && $message === "[{$mediaType}]";

        $body = match (true) {
            $isGenericMediaPlaceholder => ucfirst($mediaType).' received — see Attachments below.',
            $message !== '' => $message,
            default => '(media or non-text message)',
        };

        $ticket->replies()->create([
            'body' => $body,
            'is_internal' => false,
            'whatsapp_direction' => $direction,
            'external_sender_name' => $this->externalSenderName($data, $direction, $senderType),
        ]);

        // A customer messaging again on a thread staff had already
        // wrapped up is a real reopening of the issue, not a stray note.
        if ($direction === 'inbound' && in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $ticket->update(['status' => TicketStatus::Open]);
        }

        if (! empty($data['media_id']) && $direction === 'inbound') {
            ImportWhatsappTicketMedia::dispatch(
                $ticket->id,
                $data['conversation_id'],
                $data['media_id'],
                $mediaType ?? 'file',
            );
        }

        return response()->json(['status' => 'reply_added', 'ticket_id' => $ticket->id]);
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
    private function handleUnmatchedNumber(array $data, string $direction, string $senderType): JsonResponse
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
            $this->recordLeadMessage($lead, $data, $direction, $senderType);

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

        $this->recordLeadMessage($lead, $data, $direction, $senderType);

        return response()->json(['status' => 'lead_created', 'lead_id' => $lead->id]);
    }

    /**
     * Shared by both branches above: adds the note, schedules a follow-up if
     * the AI just replied, and — new — logs a VisibilityAuditTouch when a VA
     * cohort lead's real customer sends a reply, so the funnel dashboard can
     * flag "customer replied, nobody from staff has answered yet" the same
     * way it already flags a stuck landing/checkout stage. Mirrors
     * CallLogController::logVisibilityAuditTouch()'s own auto-logging
     * pattern — no new staff-facing UI, pure aggregation off traffic this
     * webhook already receives.
     */
    private function recordLeadMessage(Lead $lead, array $data, string $direction, string $senderType): void
    {
        if (blank($data['message'] ?? null)) {
            return;
        }

        $lead->notes()->create([
            'user_id' => null,
            'body' => $this->noteBody($data['message'], $direction, $senderType, $data['sender_name'] ?? null),
        ]);

        $this->scheduleFollowUpIfAiReplied($lead, $direction, $senderType);

        if ($direction === 'inbound' && $senderType === 'customer' && $this->vaMetrics->isVisibilityAuditCohort($lead)) {
            VisibilityAuditTouch::create([
                'lead_id' => $lead->id,
                'touch_type' => VisibilityAuditTouchType::CustomerReply,
                'channel' => VisibilityAuditTouchChannel::CustomerWhatsapp,
                'actor_user_id' => null,
                'occurred_at' => now(),
                'success' => true,
                'meta' => ['conversation_id' => $data['conversation_id']],
            ]);
        }
    }

    /**
     * The wadesk.in after-hours AI assistant's reply is only ever a holding
     * message — it never books anything or closes a deal. Without this, a
     * lead it replies to has no reason to ever surface in the "Follow-ups
     * due" queue (Lead index, Sales Dashboard, My Day) that reps already
     * check, and can silently sit unfollowed. Confirmed as a real problem
     * via production data 2026-08-20, not assumed: of 14 leads the AI had
     * replied to, 8 (57%) had received zero human follow-up at all, some
     * waiting 2+ days. Sets `next_follow_up_at` to right now — by the time
     * staff next open the CRM (typically the next business morning, per
     * the same data), it already reads as overdue and sorts to the top of
     * the priority list (`Lead::priorityScore()`), rather than waiting for
     * someone to notice a fresh WhatsApp message on their own. Only fires
     * once: a lead that already has a follow-up scheduled (staff set one,
     * or an earlier AI reply already set this) is left untouched, matching
     * how every other write to this field in this app is manual/one-shot —
     * there's no existing auto-clear-on-contact mechanism for
     * `Lead.next_follow_up_at` to disrupt.
     */
    private function scheduleFollowUpIfAiReplied(Lead $lead, string $direction, string $senderType): void
    {
        if ($direction !== 'outbound' || $senderType !== 'ai') {
            return;
        }

        if ($lead->next_follow_up_at !== null) {
            return;
        }

        $lead->update(['next_follow_up_at' => now()]);
    }

    /**
     * A Note has no direction/author field of its own (unlike TicketReply,
     * which got new columns for this) — a WhatsApp outbound message is
     * distinguished by a short prefix on the body instead, so the timeline
     * still reads clearly without a schema change to a much more widely
     * shared model.
     */
    private function noteBody(string $message, string $direction, string $senderType, ?string $senderName): string
    {
        if ($direction === 'inbound') {
            return $message;
        }

        $label = $senderType === 'ai' ? 'AI Assistant (auto-reply)' : ($senderName ?: 'Staff');

        return "[Sent via WhatsApp by {$label}]\n{$message}";
    }

    private function externalSenderName(array $data, string $direction, string $senderType): string
    {
        if ($direction === 'inbound') {
            return ($data['contact_name'] ?? null) ?: 'Customer';
        }

        return $senderType === 'ai' ? 'AI Assistant (WhatsApp)' : (($data['sender_name'] ?? null) ?: 'Support agent');
    }

    /**
     * Checks every place a client's number can legitimately be recorded:
     * the Customer's own phone, their alternate_phone (2026-08-13 — real
     * incident: a client messaged from a second number that was only ever
     * recorded as alternate_phone, and this lookup didn't check it yet, so
     * the message wrongly created a Lead instead of a Ticket), and finally
     * an individual Contact's phone (a person at that company, distinct
     * from the company-level number).
     */
    private function findCustomer(string $rawPhone): ?Customer
    {
        $digits = Phone::digits($rawPhone);
        $last10 = Phone::last10($rawPhone);

        $customer = Customer::where('phone', $digits)->first()
            ?? Customer::where('phone', '+'.$digits)->first()
            ?? Customer::where('phone', 'LIKE', '%'.$last10)->first()
            ?? Customer::where('alternate_phone', $digits)->first()
            ?? Customer::where('alternate_phone', '+'.$digits)->first()
            ?? Customer::where('alternate_phone', 'LIKE', '%'.$last10)->first();

        return $customer ?? Contact::where('phone', 'LIKE', '%'.$last10)->first()?->customer;
    }
}
