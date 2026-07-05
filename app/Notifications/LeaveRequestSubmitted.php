<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $requester = $this->leaveRequest->user?->name ?? 'Someone';
        $start = $this->leaveRequest->start_date->format('d M');
        $end = $this->leaveRequest->end_date->format('d M');

        return [
            'type' => 'leave_request_submitted',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "Leave request: {$requester}, {$start} – {$end}",
            'url' => route('leave-requests.approvals'),
        ];
    }
}
