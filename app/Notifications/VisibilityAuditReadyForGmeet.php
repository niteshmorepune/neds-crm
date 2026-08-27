<?php

namespace App\Notifications;

use App\Models\VisibilityAuditPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the matched Lead's owner the moment staff marks a Visibility
 * Audit as ready (step 3 of the post-payment conversion pipeline,
 * VisibilityAuditPurchaseController::markReady()) — the whole point of
 * this step is that the team doesn't just quietly finish the audit and
 * forget to follow up, so this fires proactively rather than depending on
 * someone noticing a status change.
 */
class VisibilityAuditReadyForGmeet extends Notification
{
    use Queueable;

    public function __construct(public VisibilityAuditPurchase $purchase) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->purchase->lead?->name ?? $this->purchase->payer_name ?? 'the customer';

        return [
            'type' => 'visibility_audit_ready_for_gmeet',
            'purchase_id' => $this->purchase->id,
            'lead_id' => $this->purchase->lead_id,
            'lead_name' => $this->purchase->lead?->name ?? $this->purchase->payer_name,
            'tier' => $this->purchase->tier?->value,
            'message' => "The Visibility Audit for {$name} is ready — schedule the 15-min Gmeet before sharing it.",
            'url' => $this->purchase->lead_id ? route('leads.show', $this->purchase->lead_id) : null,
        ];
    }
}
