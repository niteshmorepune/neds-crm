<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A Lead that staff had previously soft-deleted just sent another WhatsApp
 * message on the same conversation. See
 * WhatsappWebhookController::handleUnmatchedNumber() — before this existed,
 * the lookup there only checked non-trashed leads, so a message on a
 * deleted lead's conversation fell through to Lead::create() and threw a
 * unique-constraint violation on whatsapp_conversation_id every time,
 * silently dropping the message forever (real incident 2026-09-16, leads
 * #421/#422). Restoring automatically is the only way to keep the
 * conversation working, but the original delete could have been
 * intentional (e.g. spam, a duplicate cleanup) — this notifies Admin/
 * Manager so they can review and re-delete if so, mirroring how
 * PossibleDuplicateLeadNotification handles the same "automated action
 * taken on an ambiguous situation, human reviews after the fact" shape.
 */
class LeadRestoredByIncomingMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead_restored_by_incoming_message',
            'lead_id' => $this->lead->id,
            'message' => "{$this->lead->name} was previously deleted, but just messaged again on WhatsApp — automatically restored. Review and re-delete if that was intentional.",
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
