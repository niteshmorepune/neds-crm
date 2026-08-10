<?php

namespace App\Mail;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your '.config('company.name').' partner portal password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.partner-password-reset',
            with: ['url' => route('partner-portal.password.reset', $this->token)],
        );
    }
}
