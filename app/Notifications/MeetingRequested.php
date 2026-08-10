<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the project's scheduling contact (see Project::schedulingContact())
 * whenever a client requests a meeting from the portal — fired regardless
 * of whether a real Calendar event was auto-created (Meeting.meet_link set)
 * or the request needs manual scheduling (meet_link null), so staff always
 * know a client is waiting either way.
 */
class MeetingRequested extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $customer = $this->meeting->meetable;
        $when = $this->meeting->occurred_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A');

        $message = $this->meeting->meet_link
            ? "{$customer?->company_name} requested a meeting for {$when} — Meet link created automatically"
            : "{$customer?->company_name} requested a meeting for {$when} — needs manual scheduling";

        return [
            'type' => 'meeting_requested',
            'meeting_id' => $this->meeting->id,
            'customer_id' => $customer?->id,
            'message' => $message,
            'url' => $customer ? route('clients.show', $customer->id) : null,
        ];
    }
}
