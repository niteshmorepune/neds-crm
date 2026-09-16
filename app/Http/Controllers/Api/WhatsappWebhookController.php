<?php

namespace App\Http\Controllers\Api;

use App\Actions\FlagPossibleDuplicateLead;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Http\Controllers\Controller;
use App\Jobs\ImportWhatsappTicketMedia;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadWhatsappConversation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VisibilityAuditTouch;
use App\Models\WadeskMessageLog;
use App\Notifications\LeadRestoredByIncomingMessageNotification;
use App\Notifications\MergedLeadMessagedNotification;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly VisibilityAuditFunnelMetrics $vaMetrics,
        private readonly FlagPossibleDuplicateLead $duplicateFlagger,
    ) {}

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

        // A missing whatsapp_number (older wadesk.in build, or a
        // not-yet-configured line) defaults to the support-line behavior for
        // backward compatibility.
        $supportNumber = config('services.wadesk.support_number');
        $isSupportLine = blank($data['whatsapp_number'] ?? null) || $data['whatsapp_number'] === $supportNumber;

        // A known Customer's phone is checked BEFORE deciding what to do
        // with a non-support line — regardless of which of NEDS' numbers
        // they messaged, someone who's already a client never becomes a
        // duplicate Lead (real incident 2026-09-03: an existing client,
        // NSS Secure Solutions, messaged a non-support line and got filed
        // as a brand-new Lead). Only a phone with NO matching Customer at
        // all falls through to Lead capture.
        $customer = $this->findCustomer($data['phone']);

        if (! $customer) {
            return $this->handleUnmatchedNumber($data, $direction, $senderType);
        }

        // A known customer on a line OTHER than Support (e.g. Marketing) —
        // still never a Ticket (owner-confirmed 2026-08-03: Marketing-line
        // traffic is pre-sale, not support), but also never a Lead now that
        // they're a client. Logged to their own timeline instead, so
        // Sales/staff still see the inquiry.
        if (! $isSupportLine) {
            return $this->recordCustomerMessage($customer, $data, $direction, $senderType);
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
     * duplicate-lead pattern found in production 2026-08-13). That only
     * catches an EXACT phone match, though — when the alternate number is
     * one findOpenByPhone can't match (the WhatsApp account's own number,
     * never typed into any form), a genuinely brand-new Lead gets created
     * instead. flagPossibleDuplicate() below is the after-the-fact catch
     * for that case: a name-similarity check against recent Leads, alerting
     * staff rather than silently letting a second, contextless AI auto-reply
     * go unnoticed for hours (see App\Services\DuplicateLeadDetector).
     *
     * The initial conversation_id lookup deliberately checks trashed leads
     * too (real incident 2026-09-16, leads #421/#422): whatsapp_conversation_id
     * is a unique column that survives a soft delete, so a non-trashed-only
     * lookup fell through to Lead::create() on every later message once a
     * lead was deleted, hit that unique constraint, threw, and silently
     * dropped the message — repeating on every subsequent message, not just
     * once. A staff delete doesn't mean "this person should never reach us
     * again," so a trashed match is restored rather than left permanently
     * broken; see restoreIfTrashed() for why that's paired with a staff
     * notification instead of a silent auto-fix.
     *
     * A trashed match is NOT always an incidental delete, though — a real
     * follow-on incident the SAME day (#421/#422 again) showed both had
     * actually been correctly merged away via App\Actions\MergeLeads, after
     * the duplicate detector (#197) had already flagged and notified staff,
     * who had already reviewed and merged. Restoring unconditionally
     * silently undid that legitimate consolidation. See
     * survivingPrimaryOf()/recordMessageOnMergedAwayLead() for the branch
     * that now catches this case first — restoreIfTrashed() only ever runs
     * for a trashed lead with no still-active merge target, i.e. a
     * genuinely incidental delete, exactly its original, unchanged
     * behavior for that case.
     *
     * A future merge of two leads that each had their own live conversation
     * no longer needs any of the above at all: App\Actions\MergeLeads now
     * records the duplicate's conversation_id in lead_whatsapp_conversations
     * against the surviving primary at merge time, and the very first check
     * below resolves it directly — the trashed-lead branch only exists for
     * an already-stranded conversation from before that mapping existed, or
     * a genuinely incidental delete.
     */
    private function handleUnmatchedNumber(array $data, string $direction, string $senderType): JsonResponse
    {
        $mappedLead = LeadWhatsappConversation::where('conversation_id', $data['conversation_id'])->first()?->lead;

        if ($mappedLead !== null) {
            $this->recordLeadMessage($mappedLead, $data, $direction, $senderType);

            return response()->json(['status' => 'lead_note_added', 'lead_id' => $mappedLead->id, 'restored' => false]);
        }

        $lead = Lead::withTrashed()->where('whatsapp_conversation_id', $data['conversation_id'])->first();

        if ($lead !== null && $lead->trashed()) {
            $primary = $this->survivingPrimaryOf($lead);

            if ($primary !== null) {
                return $this->recordMessageOnMergedAwayLead($lead, $primary, $data, $direction, $senderType);
            }
        }

        $restored = $lead !== null && $this->restoreIfTrashed($lead);

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

            return response()->json(['status' => 'lead_note_added', 'lead_id' => $lead->id, 'restored' => $restored]);
        }

        $lead = Lead::create([
            'name' => ($data['contact_name'] ?? null) ?: 'WhatsApp Inquiry',
            'phone' => $data['phone'],
            'source' => LeadSource::Whatsapp->value,
            'status' => LeadStatus::New->value,
            'owner_id' => null,
            'whatsapp_conversation_id' => $data['conversation_id'],
        ]);

        $this->flagPossibleDuplicate($lead);

        $this->recordLeadMessage($lead, $data, $direction, $senderType);

        return response()->json(['status' => 'lead_created', 'lead_id' => $lead->id]);
    }

    /**
     * A side effect only, never a precondition — the Lead above is already
     * created and this runs after it. Wrapped so a bug in the detector can
     * never turn a "lead_created" response into a 500 (same "AI/integration
     * failure must never break a core workflow" convention every other
     * best-effort side effect in this app follows). See
     * App\Services\DuplicateLeadDetector's own docblock for why this is
     * deliberately an after-the-fact alert, not a check that could gate the
     * lead or the (already independently-fired) WhatsApp auto-reply.
     */
    private function flagPossibleDuplicate(Lead $lead): void
    {
        try {
            $this->duplicateFlagger->handle($lead);
        } catch (Throwable $e) {
            Log::warning('FlagPossibleDuplicateLead failed for lead '.$lead->id.': '.$e->getMessage());
        }
    }

    /**
     * Restores a soft-deleted Lead so its conversation stops permanently
     * failing with a unique-constraint violation (see
     * handleUnmatchedNumber()'s own docblock). Deliberately restores rather
     * than routing the message elsewhere — the delete could have been a
     * mistake, a stale cleanup, or genuinely intentional (spam, a merge
     * duplicate not caught here), and there's no reliable signal on the
     * Lead itself to tell those apart; restoring keeps the conversation
     * working either way, and the notification below is what lets staff
     * correct it (re-delete) if the original delete really was intentional
     * — same "automate the recoverable part, let a human judge the
     * ambiguous part" shape as FlagPossibleDuplicateLead. Leaves a
     * breadcrumb Note on the lead itself (mirrors MergeLeads' own
     * "leaves a breadcrumb note" convention) in addition to the
     * notification, so the context survives even if the notification is
     * missed or its recipient list is empty.
     */
    private function restoreIfTrashed(Lead $lead): bool
    {
        if (! $lead->trashed()) {
            return false;
        }

        $deletedAt = $lead->deleted_at;
        $lead->restore();

        $lead->notes()->create([
            'user_id' => null,
            'body' => "This lead was deleted on {$deletedAt} but the contact just messaged again on WhatsApp — automatically restored.",
        ]);

        $this->notifyLeadRestored($lead);

        return true;
    }

    /**
     * Best-effort, same reasoning as flagPossibleDuplicate() above — a
     * notification failure must never turn a successful restore back into
     * a broken conversation.
     */
    private function notifyLeadRestored(Lead $lead): void
    {
        try {
            $notification = new LeadRestoredByIncomingMessageNotification($lead);

            User::where('is_active', true)
                ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
                ->get()
                ->each(fn (User $user) => $user->notify($notification));
        } catch (Throwable $e) {
            Log::warning('LeadRestoredByIncomingMessageNotification failed for lead '.$lead->id.': '.$e->getMessage());
        }
    }

    /**
     * possible_duplicate_of_lead_id survives both a merge (App\Actions\
     * MergeLeads never touches it) and a later restore untouched, making it
     * a reliable existing signal for "this Lead was part of a
     * duplicate-detector-reviewed pair" — see the 2026-09-16 follow-on
     * incident write-up on handleUnmatchedNumber() for why this check
     * exists. Deliberately does not try to be more certain than that
     * signal actually is: the flagged candidate isn't guaranteed to be the
     * literal Lead a human chose as merge primary (staff could have merged
     * the other way, or into a third Lead entirely) — but treating it as
     * "probably related, hold for review" is safe either way, since the
     * only two outcomes this check picks between are "restore automatically"
     * and "attach the message to this Lead and ask a human," never anything
     * destructive. Returns null (falls through to the original
     * restoreIfTrashed() behavior) whenever there's no non-trashed
     * candidate to hold the message against.
     */
    private function survivingPrimaryOf(Lead $lead): ?Lead
    {
        if ($lead->possible_duplicate_of_lead_id === null) {
            return null;
        }

        return Lead::find($lead->possible_duplicate_of_lead_id);
    }

    /**
     * The held-for-review branch: a trashed Lead's conversation received a
     * new message, but it was merged away, not incidentally deleted (see
     * survivingPrimaryOf()). Never restores $trashedLead and never touches
     * Lead::create() — both would either re-fragment a resolved duplicate
     * or hit the exact unique-constraint crash this whole incident is
     * about, since $trashedLead still physically holds this
     * conversation_id. The message lands on $primary's own timeline
     * instead, clearly labeled, so nothing is lost and staff see it
     * immediately without having to know to look for it; a notification
     * carries the full context needed to decide whether this should become
     * a separate Lead again. Deliberately skips scheduleFollowUpIfAiReplied()/
     * the VisibilityAuditTouch logging recordLeadMessage() would normally
     * do — attributing those to $primary would assume the review's outcome
     * before a human has actually made the call.
     */
    private function recordMessageOnMergedAwayLead(Lead $trashedLead, Lead $primary, array $data, string $direction, string $senderType): JsonResponse
    {
        if (filled($data['message'] ?? null)) {
            $header = "[WhatsApp on previously-merged lead #{$trashedLead->id} \"{$trashedLead->name}\" ({$trashedLead->phone}), merged into this record on {$trashedLead->deleted_at} — held for review, not auto-restored]";

            $primary->notes()->create([
                'user_id' => null,
                'body' => $header."\n".$this->noteBody($data['message'], $direction, $senderType, $data['sender_name'] ?? null),
            ]);
        }

        $this->notifyMergedLeadMessaged($trashedLead, $primary);

        return response()->json([
            'status' => 'merged_lead_held_for_review',
            'lead_id' => $primary->id,
            'trashed_lead_id' => $trashedLead->id,
        ]);
    }

    /**
     * Best-effort, same reasoning as flagPossibleDuplicate()/notifyLeadRestored()
     * above — a notification failure must never break the core webhook flow.
     */
    private function notifyMergedLeadMessaged(Lead $trashedLead, Lead $primary): void
    {
        try {
            $notification = new MergedLeadMessagedNotification($trashedLead, $primary);

            User::where('is_active', true)
                ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
                ->get()
                ->each(fn (User $user) => $user->notify($notification));
        } catch (Throwable $e) {
            Log::warning('MergedLeadMessagedNotification failed for lead '.$trashedLead->id.': '.$e->getMessage());
        }
    }

    /**
     * A known Customer messaged a non-support WhatsApp line (e.g.
     * Marketing) — never creates a Lead for them (see handle()), just adds
     * the message to their own timeline so Sales/staff still see it.
     */
    private function recordCustomerMessage(Customer $customer, array $data, string $direction, string $senderType): JsonResponse
    {
        if (blank($data['message'] ?? null)) {
            return response()->json(['status' => 'ignored', 'reason' => 'no_message_body']);
        }

        $customer->notes()->create([
            'user_id' => null,
            'body' => $this->noteBody($data['message'], $direction, $senderType, $data['sender_name'] ?? null),
        ]);

        return response()->json(['status' => 'customer_note_added', 'customer_id' => $customer->id]);
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
     * See Customer::findByPhone() for the actual matching logic (extracted
     * there 2026-09-10 so CallLogController's wadesk-call-sync endpoint
     * shares the identical resolution instead of drifting).
     */
    private function findCustomer(string $rawPhone): ?Customer
    {
        return Customer::findByPhone($rawPhone);
    }
}
