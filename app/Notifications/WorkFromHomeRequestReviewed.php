<?php

namespace App\Notifications;

use App\Enums\WorkFromHomeRequestStatus;
use App\Models\WorkFromHomeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkFromHomeRequestReviewed extends Notification
{
    use Queueable;

    public function __construct(public WorkFromHomeRequest $workFromHomeRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $verb = $this->workFromHomeRequest->status === WorkFromHomeRequestStatus::Approved ? 'approved' : 'rejected';
        $start = $this->workFromHomeRequest->start_date->format('d M');
        $end = $this->workFromHomeRequest->end_date->format('d M');

        return [
            'type' => 'work_from_home_request_reviewed',
            'work_from_home_request_id' => $this->workFromHomeRequest->id,
            'message' => "Your WFH request ({$start} – {$end}) was {$verb}",
            'url' => route('work-from-home.index'),
        ];
    }
}
