<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Models\Concerns\HasGstTotals;
use App\Models\Concerns\LogsActivity;
use App\Notifications\NewQuotationNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    use HasFactory, HasGstTotals, LogsActivity;

    protected static function booted(): void
    {
        static::created(function (Quotation $quotation) {
            $ownerId = $quotation->deal_id
                ? Deal::where('id', $quotation->deal_id)->value('owner_id')
                : Customer::where('id', $quotation->customer_id)->value('owner_id');
            if ($ownerId) {
                User::find($ownerId)?->notify(new NewQuotationNotification($quotation));
            }
        });
    }

    protected $fillable = [
        'number', 'customer_id', 'deal_id', 'status', 'place_of_supply_state_code',
        'is_intra_state', 'is_gst_exempt', 'subtotal', 'discount', 'taxable_total', 'cgst_total',
        'sgst_total', 'igst_total', 'round_off', 'total', 'terms', 'validity_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'is_intra_state' => 'boolean',
            'is_gst_exempt' => 'boolean',
            'validity_date' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'taxable_total' => 'integer',
            'cgst_total' => 'integer',
            'sgst_total' => 'integer',
            'igst_total' => 'integer',
            'round_off' => 'integer',
            'total' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(QuotationMilestone::class)->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return $this->status === QuotationStatus::Draft;
    }
}
