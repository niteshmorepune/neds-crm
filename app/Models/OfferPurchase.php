<?php

namespace App\Models;

use App\Enums\OfferKey;
use App\Enums\OfferPurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_key',
        'price_paise',
        'status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'lead_id',
        'payer_name',
        'payer_phone',
        'payer_email',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'offer_key' => OfferKey::class,
            'status' => OfferPurchaseStatus::class,
            'price_paise' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
