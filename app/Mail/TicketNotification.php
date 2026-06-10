<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'created'|'replied'|'resolved'  $kind
     */
    public function __construct(
        public Ticket $ticket,
        public string $kind,
        public ?TicketReply $reply = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->kind) {
            'created' => "We've received your ticket: {$this->ticket->subject}",
            'replied' => "Update on your ticket: {$this->ticket->subject}",
            'resolved' => "Resolved: {$this->ticket->subject}",
            default => "Ticket update: {$this->ticket->subject}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.ticket-notification');
    }
}
