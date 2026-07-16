<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'service_id', 'frequency', 'day_of_month',
        'start_date', 'end_date', 'next_run_on', 'is_active',
        'last_reminder_sent_at', 'discount', 'is_gst_exempt', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_on' => 'date',
            'last_reminder_sent_at' => 'date',
            'is_active' => 'boolean',
            'discount' => 'integer',
            'is_gst_exempt' => 'boolean',
            'day_of_month' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('sort_order');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Active templates whose next run is due on or before the given date. */
    public function scopeDue(Builder $query, $date): Builder
    {
        return $query->where('is_active', true)->whereDate('next_run_on', '<=', $date);
    }

    /** Excludes templates that have naturally ended (see hasEnded()) — the main Recurring Invoices list defaults to this to avoid piling up with finished one-cycle templates. */
    public function scopeNotEnded(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_active', true)
                ->orWhereNull('end_date')
                ->orWhereDate('end_date', '>=', now()->toDateString());
        });
    }

    /**
     * Distinguishes an inactive template that ran its course (end_date already
     * passed — nothing wrong, no action needed) from one a human paused via
     * the Pause/Activate button (end_date blank or still in the future) —
     * both just set is_active=false, but they read very differently to staff.
     */
    public function hasEnded(): bool
    {
        return ! $this->is_active && $this->end_date !== null && $this->end_date->isPast();
    }

    /**
     * Client-facing "where are things with this service" status for the
     * Services tab — time + payment based, distinct from is_active (which
     * only governs whether the system keeps auto-billing). One of:
     * 'upcoming' | 'active' | 'on_hold' | 'payment_received' |
     * 'payment_pending' | 'ended'.
     *
     * $revealPaymentStatus should be false for a viewer without invoice
     * access (e.g. Support) — a period that's over falls back to 'ended'
     * instead of exposing whether the invoice was actually paid.
     */
    public function dashboardStatus(bool $revealPaymentStatus = true): string
    {
        if ($this->start_date->isFuture()) {
            return 'upcoming';
        }

        $periodOver = $this->end_date !== null && $this->end_date->isPast();

        if (! $periodOver) {
            return $this->is_active ? 'active' : 'on_hold';
        }

        if (! $revealPaymentStatus) {
            return 'ended';
        }

        $latestInvoice = $this->invoices->sortByDesc('issue_date')->first();

        if (! $latestInvoice) {
            return 'ended';
        }

        return $latestInvoice->status === InvoiceStatus::Paid ? 'payment_received' : 'payment_pending';
    }
}
