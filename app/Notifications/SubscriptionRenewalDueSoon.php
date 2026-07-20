<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalDueSoon extends Notification
{
    use Queueable;

    public function __construct(public Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $renewsOn = $this->subscription->renewal_date->format('d M Y');

        return [
            'type' => 'subscription_renewal_due_soon',
            'subscription_id' => $this->subscription->id,
            'message' => "{$this->subscription->name} renews {$renewsOn} — ".Money::format($this->subscription->cost),
            'url' => route('subscriptions.index'),
        ];
    }
}
