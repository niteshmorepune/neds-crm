<?php

namespace App\Notifications;

use App\Models\ContentPiece;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FestivalGreetingDrafted extends Notification
{
    use Queueable;

    public function __construct(public ContentPiece $contentPiece) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->contentPiece->loadMissing('project.customer', 'festival');

        $festivalName = $this->contentPiece->festival?->name ?? 'Festival';
        $client = $this->contentPiece->project->customer?->company_name ?? 'a client';

        return [
            'type' => 'festival_greeting_drafted',
            'content_piece_id' => $this->contentPiece->id,
            'message' => "🎉 {$festivalName} greeting drafted for {$client}",
            'url' => route('projects.show', $this->contentPiece->project_id).'#content',
        ];
    }
}
