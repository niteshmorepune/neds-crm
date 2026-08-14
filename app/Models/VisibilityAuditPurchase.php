<?php

namespace App\Models;

use App\Enums\VisibilityAuditTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A completed Razorpay payment against the Visibility Audit offer landing
 * page (/offers/visibility-audit). Recorded purely for idempotency
 * (razorpay_payment_id is unique — same guard pattern as Payment's
 * gateway_payment_id) and reconciliation; the real follow-up signal lives
 * on the matched Lead's note, not this row.
 */
class VisibilityAuditPurchase extends Model
{
    protected $fillable = [
        'tier',
        'amount_paise',
        'razorpay_payment_id',
        'razorpay_order_id',
        'payer_name',
        'payer_phone',
        'payer_email',
        'gbp_url',
        'website_url',
        'lead_id',
    ];

    protected function casts(): array
    {
        return [
            'tier' => VisibilityAuditTier::class,
            'amount_paise' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
