<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired at every portal-enabled contact of the project's customer when a
 * note marked visible_to_client is posted (see RecordNotes::addNote()).
 */
class ProjectUpdatePosted extends Notification
{
    use Queueable;

    public function __construct(public Project $project) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'project_update',
            'project_id' => $this->project->id,
            'message' => "New update on project: {$this->project->name}",
            'url' => route('portal.projects.show', $this->project->id),
        ];
    }
}
