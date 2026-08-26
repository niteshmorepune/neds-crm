<?php

namespace App\Notifications;

use App\Models\WorkFromHomeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkFromHomeRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(public WorkFromHomeRequest $workFromHomeRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $requester = $this->workFromHomeRequest->user?->name ?? 'Someone';
        $start = $this->workFromHomeRequest->start_date->format('d M');
        $end = $this->workFromHomeRequest->end_date->format('d M');

        return [
            'type' => 'work_from_home_request_submitted',
            'work_from_home_request_id' => $this->workFromHomeRequest->id,
            'message' => "WFH request: {$requester}, {$start} – {$end}",
            'url' => route('work-from-home.approvals'),
        ];
    }
}
