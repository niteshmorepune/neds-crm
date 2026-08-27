<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisibilityAuditPurchase;

/**
 * A purchase is only ever reached through its matched Lead — same
 * delegate-to-the-parent shape as ProjectDeliverablePolicy. Used by the
 * generic AttachmentController (download/destroy on the report file) and
 * step 4's own upload/send actions.
 */
class VisibilityAuditPurchasePolicy
{
    public function view(User $user, VisibilityAuditPurchase $purchase): bool
    {
        return $purchase->lead !== null && app(LeadPolicy::class)->view($user, $purchase->lead);
    }

    /**
     * manageMeetings(), not update() — same reasoning as
     * LeadController::markVisibilityAuditReady(): uploading/sending the
     * report is meeting-adjacent (it's the direct follow-through on the
     * Gmeet), not general lead editing.
     */
    public function update(User $user, VisibilityAuditPurchase $purchase): bool
    {
        return $purchase->lead !== null && app(LeadPolicy::class)->manageMeetings($user, $purchase->lead);
    }
}
