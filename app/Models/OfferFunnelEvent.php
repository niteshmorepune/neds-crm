<?php

namespace App\Models;

use App\Enums\OfferFunnelEventType;
use App\Enums\OfferKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferFunnelEvent extends Model
{
    protected $fillable = [
        'event_type',
        'offer_key',
        'lead_id',
        'nudged_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => OfferFunnelEventType::class,
            'offer_key' => OfferKey::class,
            'nudged_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
