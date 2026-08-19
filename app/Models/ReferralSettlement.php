<?php

namespace App\Models;

use App\Enums\PartnerCollectionMode;
use App\Enums\SettlementAmountSource;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (RecurringInvoice, month) — the referral share settled between
 * NEDS and a Partner for that service that month. See the migration's own
 * docblock for the full "why one symmetric table" reasoning.
 *
 * Uses LogsActivity unlike its sibling PartnerCommissionStatement (which is
 * fully system-regenerated each month, so an audit trail is less load-bearing
 * there) — a PartnerCollects row is staff-keyed money with no other source of
 * truth backing it, the same category as ClientAdvance.
 */
class ReferralSettlement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'partner_id', 'recurring_invoice_id', 'period_start',
        'flow_mode', 'billed_amount', 'share_rate', 'share_amount',
        'amount_source', 'entered_by', 'finalized_at', 'settled_at', 'settled_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'flow_mode' => PartnerCollectionMode::class,
            'billed_amount' => 'integer',
            'share_rate' => 'decimal:2',
            'share_amount' => 'integer',
            'amount_source' => SettlementAmountSource::class,
            'finalized_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * Which party owes whom for this row, derived from flow_mode rather than
     * re-matched in every view/controller that needs to know.
     */
    public function owesDirection(): string
    {
        return $this->flow_mode === PartnerCollectionMode::PartnerCollects
            ? 'partner_owes_neds'
            : 'neds_owes_partner';
    }
}
