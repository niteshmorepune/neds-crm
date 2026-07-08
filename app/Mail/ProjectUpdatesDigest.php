<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProjectUpdatesDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection  $yesterdaysDrafts  AI-drafted client-update notes created yesterday
     * @param  Collection  $staleDrafts  AI-drafted notes still unapproved after $staleDays
     * @param  Collection  $quietProjects  Array of ['project' => Project, 'lastActivityAt' => ?Carbon]
     */
    public function __construct(
        public User $user,
        public Collection $yesterdaysDrafts,
        public Collection $staleDrafts,
        public Collection $quietProjects,
        public int $staleDays,
        public int $quietDays,
    ) {}

    public function envelope(): Envelope
    {
        $flagged = $this->staleDrafts->count() + $this->quietProjects->count();

        return new Envelope(
            subject: $flagged > 0
                ? "Project updates digest — {$flagged} item(s) need attention | NEDS CRM"
                : 'Project updates digest | NEDS CRM',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.project-updates-digest');
    }
}
