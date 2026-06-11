<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasGstTotals;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, HasGstTotals, LogsActivity, SoftDeletes;

    protected $fillable = [
        'invoice_number', 'financial_year', 'customer_id', 'deal_id', 'quotation_id', 'recurring_invoice_id',
        'status', 'issue_date', 'due_date', 'place_of_supply_state_code', 'is_intra_state',
        'subtotal', 'discount', 'taxable_total', 'cgst_total', 'sgst_total', 'igst_total',
        'round_off', 'total', 'amount_paid',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'is_intra_state' => 'boolean',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'taxable_total' => 'integer',
            'cgst_total' => 'integer',
            'sgst_total' => 'integer',
            'igst_total' => 'integer',
            'round_off' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
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

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    /** True when this invoice was generated from a recurring template. */
    public function isRecurring(): bool
    {
        return $this->recurring_invoice_id !== null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_on');
    }

    public function balance(): int
    {
        return max(0, (int) $this->total - (int) $this->amount_paid);
    }

    /**
     * Recompute amount_paid from payments and set the derived status.
     * Does not override Draft/Sent until a payment exists, and never resurrects
     * a Cancelled invoice.
     */
    public function refreshPaymentStatus(): void
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return;
        }

        $paid = (int) $this->payments()->sum('amount');
        $this->amount_paid = $paid;

        if ($paid <= 0) {
            // leave as-is (draft/sent/overdue)
        } elseif ($paid >= (int) $this->total) {
            $this->status = InvoiceStatus::Paid;
        } else {
            $this->status = InvoiceStatus::PartiallyPaid;
        }

        $this->save();
    }
}
