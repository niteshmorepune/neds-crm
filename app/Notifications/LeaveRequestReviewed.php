<?php

namespace App\Notifications;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestReviewed extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leaveRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verb = $this->leaveRequest->status === LeaveRequestStatus::Approved ? 'approved' : 'rejected';
        $start = $this->leaveRequest->start_date->format('d M');
        $end = $this->leaveRequest->end_date->format('d M');

        return [
            'type' => 'leave_request_reviewed',
            'leave_request_id' => $this->leaveRequest->id,
            'message' => "Your leave request ({$start} – {$end}) was {$verb}",
            'url' => route('leave-requests.index'),
        ];
    }
}
