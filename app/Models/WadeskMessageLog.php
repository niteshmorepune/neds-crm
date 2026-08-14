<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for wadesk.in webhook deliveries — see the migration's
 * own docblock. Rows are never read back by application code, only ever
 * inserted (and relied on for the unique-constraint race in
 * WhatsappWebhookController); safe to prune periodically if it grows large.
 */
class WadeskMessageLog extends Model
{
    protected $fillable = ['wadesk_message_id'];
}
