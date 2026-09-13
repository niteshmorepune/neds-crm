<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired by App\Console\Commands\MonitorOfferFunnelFailures when either
 * tracked failure shape (a spike in SendOfferRecommendationReadyJob's
 * wadesk.in send failures, or any Razorpay order-creation exception) has
 * just crossed its alert threshold and isn't already in cooldown. Same
 * database-only, Admin/Manager-broadcast channel as
 * LeadStagnationEscalatedNotification (SendStagnationAlerts' own
 * manager-tier escalation) — this is a systemic/operational alert, not
 * per-lead, so there's no single Lead/Deal to attach it to or link back to.
 */
class OfferFunnelFailureSpikeNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, int>  $topReasons
     */
    public function __construct(
        public string $summary,
        public array $topReasons = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'offer_funnel_failure_spike',
            'message' => $this->summary,
            'top_reasons' => $this->topReasons,
        ];
    }
}
