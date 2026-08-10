<?php

namespace App\Mail;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('company.name').' partner portal access');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.partner-invitation',
            with: ['url' => route('partner-portal.password.setup', $this->token)],
        );
    }
}
