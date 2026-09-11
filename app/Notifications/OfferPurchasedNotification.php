<?php

namespace App\Notifications;

use App\Models\OfferPurchase;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OfferPurchasedNotification extends Notification
{
    use Queueable;

    public function __construct(public OfferPurchase $purchase) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount = Money::format($this->purchase->price_paise);
        $offerName = $this->purchase->offer_key->shortLabel();

        return [
            'type' => 'offer_purchased',
            'purchase_id' => $this->purchase->id,
            'message' => "New purchase: {$offerName} ({$amount})".($this->purchase->payer_name ? " — {$this->purchase->payer_name}" : ''),
            'url' => $this->purchase->lead_id ? route('leads.show', $this->purchase->lead_id) : null,
        ];
    }
}
