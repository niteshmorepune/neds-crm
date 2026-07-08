<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectDailyUpdateDrafted extends Notification
{
    use Queueable;

    public function __construct(public Project $project, public string $date) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_daily_update_drafted',
            'project_id' => $this->project->id,
            'message' => "\u{1F4DD} Today's client update for {$this->project->name} is ready to review",
            'url' => route('projects.show', $this->project),
        ];
    }
}
