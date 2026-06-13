<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MorningDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection  $overdueTasks   Tasks past their due date, not done
     * @param  Collection  $dueTodayTasks  Tasks due today, not done
     * @param  Collection  $callFollowUps  Call logs with follow_up_at due today or earlier
     * @param  Collection  $leadFollowUps  Open leads with next_follow_up_at due today or earlier
     * @param  Collection  $dealFollowUps  Open deals with next_follow_up_at due today or earlier
     * @param  Collection  $openTickets    Tickets assigned to the user that are still open
     */
    public function __construct(
        public User $user,
        public Carbon $date,
        public Collection $overdueTasks,
        public Collection $dueTodayTasks,
        public Collection $callFollowUps,
        public Collection $leadFollowUps,
        public Collection $dealFollowUps,
        public Collection $openTickets,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your day ahead — '.$this->date->format('d M Y').' | NEDS CRM',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.morning-digest');
    }

    public function isEmpty(): bool
    {
        return $this->overdueTasks->isEmpty()
            && $this->dueTodayTasks->isEmpty()
            && $this->callFollowUps->isEmpty()
            && $this->leadFollowUps->isEmpty()
            && $this->dealFollowUps->isEmpty()
            && $this->openTickets->isEmpty();
    }
}
