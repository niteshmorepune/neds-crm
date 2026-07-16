<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasGstTotals;
use App\Models\Concerns\LogsActivity;
use App\Notifications\NewInvoiceNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, HasGstTotals, LogsActivity, SoftDeletes;

    protected $fillable = [
        'invoice_number', 'financial_year', 'customer_id', 'deal_id', 'project_id', 'quotation_id', 'recurring_invoice_id',
        'status', 'issue_date', 'due_date', 'payment_promised_date', 'payment_promise_notified_for', 'place_of_supply_state_code', 'is_intra_state', 'is_gst_exempt',
        'subtotal', 'discount', 'taxable_total', 'cgst_total', 'sgst_total', 'igst_total',
        'round_off', 'total', 'amount_paid',
    ];

    /** System bookkeeping for the payment-promise reminder — not a meaningful change to log. */
    protected array $activityExcept = ['payment_promise_notified_for'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'payment_promised_date' => 'date',
            'payment_promise_notified_for' => 'date',
            'is_intra_state' => 'boolean',
            'is_gst_exempt' => 'boolean',
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

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice) {
            // Skip auto-generated recurring invoices — one per client per cycle would be too noisy.
            if ($invoice->recurring_invoice_id !== null) {
                return;
            }
            $ownerId = Customer::where('id', $invoice->customer_id)->value('owner_id');
            if ($ownerId) {
                User::find($ownerId)?->notify(new NewInvoiceNotification($invoice));
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function milestones(): HasMany
    {
        return $this->hasMany(QuotationMilestone::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    public function balance(): int
    {
        return max(0, (int) $this->total - (int) $this->amount_paid - $this->tdsTotal());
    }

    /** Total TDS deducted by the client across all payments on this invoice. */
    public function tdsTotal(): int
    {
        return (int) $this->payments()->sum('tds_amount');
    }

    /** A client promised payment by a date that has now passed, and it's still unpaid. */
    public function promiseBroken(): bool
    {
        return $this->payment_promised_date !== null
            && $this->payment_promised_date->isPast()
            && $this->balance() > 0;
    }

    /** Editable/deletable only before any payment is recorded. */
    public function isEditable(): bool
    {
        return in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Overdue], true)
            && $this->payments()->doesntExist();
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

        $settled = $paid + $this->tdsTotal();

        if ($settled <= 0) {
            // leave as-is (draft/sent/overdue)
        } elseif ($settled >= (int) $this->total) {
            $this->status = InvoiceStatus::Paid;
        } else {
            $this->status = InvoiceStatus::PartiallyPaid;
        }

        $this->save();
    }
}
