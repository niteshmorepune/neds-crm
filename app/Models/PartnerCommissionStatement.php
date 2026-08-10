<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerCommissionStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'period_start',
        'referred_value',
        'commission_rate',
        'commission_amount',
        'finalized_at',
        'paid_at',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'referred_value' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
