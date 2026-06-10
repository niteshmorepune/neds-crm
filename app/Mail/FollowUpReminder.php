<?php

namespace App\Mail;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FollowUpReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, Deal>  $deals
     */
    public function __construct(
        public User $user,
        public Collection $leads,
        public Collection $deals,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your follow-ups due today — NEDS CRM',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.follow-up-reminder',
        );
    }
}
