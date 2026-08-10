<?php

namespace App\Models;

use App\Enums\QuotationApprovalStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Concerns\HasGstTotals;
use App\Models\Concerns\LogsActivity;
use App\Notifications\NewQuotationNotification;
use App\Notifications\QuotationNeedsApproval;
use Illuminate\Database\Eloquent\Builder;
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
        // Without this, a freshly-built Quotation has no in-memory
        // approval_status at all until a later refresh() picks up the DB
        // column default — the created() notification check below would
        // see null, not Pending, and silently never fire.
        static::creating(function (Quotation $quotation) {
            $quotation->approval_status ??= QuotationApprovalStatus::Pending;
        });

        static::created(function (Quotation $quotation) {
            $ownerId = $quotation->ownerId();
            if ($ownerId) {
                User::find($ownerId)?->notify(new NewQuotationNotification($quotation));
            }

            if ($quotation->approval_status === QuotationApprovalStatus::Pending) {
                User::withAnyRole(UserRole::Admin, UserRole::Manager)->get()
                    ->each(fn (User $u) => $u->notify(new QuotationNeedsApproval($quotation)));
            }
        });
    }

    protected $fillable = [
        'number', 'customer_id', 'deal_id', 'status', 'place_of_supply_state_code',
        'is_intra_state', 'is_gst_exempt', 'subtotal', 'discount', 'taxable_total', 'cgst_total',
        'sgst_total', 'igst_total', 'round_off', 'total', 'terms', 'scope_of_work', 'validity_date',
        'approval_status', 'approval_notes', 'approved_by', 'approved_at', 'client_decision_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'approval_status' => QuotationApprovalStatus::class,
            'approved_at' => 'datetime',
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

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === QuotationStatus::Draft;
    }

    /**
     * The single choke point QuotationController::send() checks: a Draft
     * quotation cannot go out to the client until an Admin/Manager has
     * approved it. Once Sent, this is irrelevant — approval only ever
     * gates the first send.
     */
    public function needsApproval(): bool
    {
        return $this->status === QuotationStatus::Draft
            && $this->approval_status !== QuotationApprovalStatus::Approved;
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', QuotationStatus::Draft->value)
            ->where('approval_status', QuotationApprovalStatus::Pending->value);
    }

    public function hasRecurringItems(): bool
    {
        return $this->items->contains(fn (QuotationItem $item) => $item->is_recurring);
    }

    /**
     * The user to notify about this quotation — the deal owner if it came
     * from a deal, otherwise the customer's account owner.
     */
    public function ownerId(): ?int
    {
        return $this->deal_id
            ? Deal::where('id', $this->deal_id)->value('owner_id')
            : Customer::where('id', $this->customer_id)->value('owner_id');
    }
}
