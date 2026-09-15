<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired by App\Actions\FlagPossibleDuplicateLead once DuplicateLeadDetector
 * finds an older Lead a brand-new WhatsApp-sourced Lead (created via
 * WhatsappWebhookController::handleUnmatchedNumber()) might be the same
 * person as, under a different phone number. Same database-only,
 * Admin/Manager-broadcast channel as LeadStagnationEscalatedNotification
 * and OfferFunnelFailureSpikeNotification — chosen over notifying just the
 * older Lead's own owner because a brand-new WhatsApp-sourced Lead always
 * has owner_id = null (nobody to notify there yet), and the older Lead's
 * owner might be unavailable for a time-sensitive alert; Admin/Manager
 * already have visibility across every Lead and are the established
 * audience for both closest precedents above.
 *
 * This never merges anything itself — App\Actions\MergeLeads still does
 * that, with a human deciding. The url links straight into the merge
 * review screen with both Leads pre-selected (LeadMergeController::show()
 * already supports this via ?ids[]=), so triage is a single click away.
 */
class PossibleDuplicateLeadNotification extends Notification
{
    use Queueable;

    public function __construct(public Lead $newLead, public Lead $olderLead) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'possible_duplicate_lead',
            'new_lead_id' => $this->newLead->id,
            'older_lead_id' => $this->olderLead->id,
            'message' => "Possible duplicate: \"{$this->newLead->name}\" ({$this->newLead->phone}) just messaged on WhatsApp — may be the same person as \"{$this->olderLead->name}\" ({$this->olderLead->phone}), created {$this->olderLead->created_at->diffForHumans()}.",
            'url' => route('leads.merge.show', ['ids' => [$this->olderLead->id, $this->newLead->id]]),
        ];
    }
}
