<?php

namespace App\Notifications;

use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired at every portal-enabled contact of a customer when a quotation is
 * sent to them, so it shows up in the portal notifications center
 * alongside the existing QuotationSent email.
 */
class QuotationAwaitingDecision extends Notification
{
    use Queueable;

    public function __construct(public Quotation $quotation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount = Money::format($this->quotation->total);

        return [
            'type' => 'quotation_awaiting_decision',
            'quotation_id' => $this->quotation->id,
            'message' => "New quotation #{$this->quotation->number} ready for your review — {$amount}",
            'url' => route('portal.quotations.show', $this->quotation->id),
        ];
    }
}
