<?php

namespace App\Notifications;

use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A deal has gone quiet mid-pipeline and Claude drafted a check-in note on
 * it -- staff-only, never sent. See App\Jobs\DraftDealStallFollowUp.
 */
class DealStallFollowUpDrafted extends Notification
{
    use Queueable;

    public function __construct(public Deal $deal) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deal_stall_followup_drafted',
            'deal_id' => $this->deal->id,
            'message' => "Check-in drafted for \"{$this->deal->title}\" — gone quiet, review and send",
            'url' => route('deals.show', $this->deal->id),
        ];
    }
}
