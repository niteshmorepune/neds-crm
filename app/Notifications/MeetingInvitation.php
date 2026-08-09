<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to each internal team member invited to a client/lead meeting
 * (see MeetingImport::createMeeting()). Database only — the client's own
 * Google Calendar/Gmail invite is unaffected and continues exactly as
 * before; this is purely the additional internal-participant notice.
 */
class MeetingInvitation extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $clientName = $this->meeting->meetable instanceof Customer
            ? $this->meeting->meetable->company_name
            : $this->meeting->meetable?->name;

        return [
            'type' => 'meeting_invitation',
            'meeting_id' => $this->meeting->id,
            'client_name' => $clientName,
            'organiser_name' => $this->meeting->user?->name,
            'occurred_at' => $this->meeting->occurred_at->toIso8601String(),
            'duration_minutes' => $this->meeting->duration_minutes,
            'meet_link' => $this->meeting->meet_link,
        ];
    }
}
