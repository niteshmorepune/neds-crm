<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contact $contact, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your '.config('company.name').' portal password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.portal-password-reset',
            with: ['url' => route('portal.password.reset', $this->token)],
        );
    }
}
