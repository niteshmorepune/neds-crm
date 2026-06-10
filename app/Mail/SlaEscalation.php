<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SlaEscalation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    public function __construct(public Collection $tickets) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "SLA breach: {$this->tickets->count()} ticket(s) need attention");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sla-escalation');
    }
}
