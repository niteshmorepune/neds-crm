<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketEscalated extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $by = $this->ticket->escalatedBy?->name ?? 'Someone';

        return [
            'type' => 'ticket_escalated',
            'ticket_id' => $this->ticket->id,
            'message' => "Ticket escalated by {$by}: {$this->ticket->subject}",
            'url' => route('tickets.show', $this->ticket),
        ];
    }
}
