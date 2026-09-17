<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Lead-note counterpart to CallFollowUpAutoSet: DetectLeadNoteFollowUpCommitment
 * found a promise in a plain note (not a logged call) and set a follow-up
 * reminder on the rep's behalf — fires right away so they can review/adjust
 * it, same transparency principle as the call-log version.
 */
class LeadFollowUpAutoSet extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $when = $this->lead->next_follow_up_at?->timezone(config('app.display_timezone'))->format('d M, h:i A');

        return [
            'type' => 'lead_follow_up_auto_set',
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name,
            'message' => "AI spotted a commitment in your note on {$this->lead->name}".
                " and set a follow-up reminder for {$when}. Edit it if that's not right.",
            'next_action' => $this->lead->ai_detected_next_action,
            'follow_up_at' => $this->lead->next_follow_up_at?->toIso8601String(),
            'url' => route('leads.show', $this->lead->id),
        ];
    }
}
