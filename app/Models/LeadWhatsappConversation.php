<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ADDITIONAL WhatsApp conversation_id a Lead answers to, on top of its
 * own leads.whatsapp_conversation_id column. Populated by
 * App\Actions\MergeLeads::handle() when it merges two Leads that each
 * independently had their own live conversation — a single column can't
 * hold two values, and leaving the duplicate's conversation_id stranded on
 * the now-trashed record made it permanently unreachable by any future
 * message on that conversation (real incidents 2026-09-16, #421->#420 and
 * #422->#423; see MergeLeads::handle()'s own docblock).
 * WhatsappWebhookController::handleUnmatchedNumber() checks this mapping
 * before its own trashed-Lead lookup, so a message on an already-mapped
 * conversation attaches straight to the surviving Lead.
 */
class LeadWhatsappConversation extends Model
{
    protected $fillable = ['lead_id', 'conversation_id'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
