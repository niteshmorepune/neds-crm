<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired at every portal-enabled contact of the ticket's customer when
 * staff post a non-internal reply — mirrors the existing TicketNotification
 * mail, just also surfaced in the portal itself.
 */
class TicketReplyPosted extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_reply',
            'ticket_id' => $this->ticket->id,
            'message' => "New reply on ticket #{$this->ticket->id}: {$this->ticket->subject}",
            'url' => route('portal.tickets.show', $this->ticket->id),
        ];
    }
}
