<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class StagnationAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection  $leads  Open leads with no activity in $leadDays days
     * @param  Collection  $deals  Open deals with no activity in $dealDays days
     */
    public function __construct(
        public User $user,
        public Collection $leads,
        public Collection $deals,
        public int $leadDays,
        public int $dealDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Stagnation alert — '.($this->leads->count() + $this->deals->count()).' record(s) need attention | NEDS CRM',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.stagnation-alert');
    }
}
