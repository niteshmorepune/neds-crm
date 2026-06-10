<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contact $contact, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('company.name').' portal access');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.portal-invitation',
            with: ['url' => route('portal.password.setup', $this->token)],
        );
    }
}
