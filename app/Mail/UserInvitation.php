<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('company.name').' CRM account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user-invitation',
            with: ['url' => route('password.reset', ['token' => $this->token, 'email' => $this->user->email])],
        );
    }
}
