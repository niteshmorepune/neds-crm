<?php

namespace App\Models;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hit on a Visibility Audit funnel-tracking redirect
 * (VisibilityAuditFunnelTrackingController) — the landing-page entry link
 * from Meta's Lead Ads thank-you screen, or the payment-page CTA on the
 * landing page itself. lead_id is set only when the visitor arrived with a
 * `?lead=` reference the CRM already recognises (currently only possible on
 * the landing→payment hop, since Meta's own thank-you-screen button is a
 * static URL with no per-submission personalisation) — a null lead_id still
 * counts toward the aggregate funnel numbers, it just can't be matched to a
 * specific Lead for a recovery follow-up.
 */
class VisibilityAuditFunnelEvent extends Model
{
    protected $fillable = [
        'event_type',
        'tier',
        'lead_id',
        'nudged_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => VisibilityAuditFunnelEventType::class,
            'tier' => VisibilityAuditTier::class,
            'nudged_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
