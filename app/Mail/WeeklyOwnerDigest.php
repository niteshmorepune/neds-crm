<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class WeeklyOwnerDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Carbon $date,
        public string $summary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your week ahead — '.$this->date->format('d M Y').' | NEDS CRM',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.weekly-owner-digest');
    }
}
