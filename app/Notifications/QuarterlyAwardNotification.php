<?php

namespace App\Notifications;

use App\Models\QuarterlyAward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuarterlyAwardNotification extends Notification
{
    use Queueable;

    public function __construct(public QuarterlyAward $award) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quarterly_award',
            'quarterly_award_id' => $this->award->id,
            'message' => "You've been recognized as {$this->award->title()} — {$this->award->periodLabel()}!",
            'url' => route('quarterly-awards.certificate', $this->award->id),
        ];
    }
}
