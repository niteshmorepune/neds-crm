<?php

namespace App\Mail;

use App\Models\Note;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectDailyUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Project $project, public Note $note) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Project update — {$this->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.project-daily-update');
    }
}
