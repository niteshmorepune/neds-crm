<?php

namespace App\Models;

use App\Enums\ContractRenewalStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PartnerCollectionMode;
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
        'customer_id', 'quotation_id', 'service_id', 'project_id', 'frequency', 'day_of_month',
        'start_date', 'end_date', 'next_run_on', 'is_active', 'renewal_status',
        'last_reminder_sent_at', 'renewal_reminder_sent_for', 'discount', 'is_gst_exempt', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'renewal_status' => ContractRenewalStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_on' => 'date',
            'last_reminder_sent_at' => 'date',
            'renewal_reminder_sent_for' => 'date',
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

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Which Project this template's generated invoices should be attributed
     * to, so they stay findable even when reseller billing (see
     * Customer::billingTarget()) means the invoice's own customer_id is a
     * different Customer than the one this work is actually for.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('sort_order');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Active templates whose next run is due on or before the given date.
     * Also excludes anything whose next_run_on has drifted past its own
     * end_date — this can happen if a template was manually reactivated
     * (via the Pause/Activate toggle) right after generateNow() correctly
     * auto-deactivated it for having exhausted its billing window; without
     * this guard, the next scheduled run would generate a duplicate invoice
     * for a period the template was never meant to bill again.
     */
    public function scopeDue(Builder $query, $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('next_run_on', '<=', $date)
            ->where(function (Builder $q) {
                $q->whereNull('end_date')->orWhereColumn('next_run_on', '<=', 'end_date');
            })
            // A PartnerCollects client gets no NEDS invoice at all (owner's
            // explicit call) — real billing for these is tracked entirely via
            // ReferralSettlement, never through GenerateRecurringInvoices.
            // Enforced here (the single scope every real-billing caller uses)
            // rather than only in the command, so this can never be bypassed
            // by a future caller of scopeDue().
            ->whereDoesntHave('customer', fn (Builder $q) => $q->where(
                'partner_collection_mode', PartnerCollectionMode::PartnerCollects->value
            ));
    }

    /**
     * True when this template is active but its next scheduled run has
     * drifted past its own end_date — the "reactivated after auto-pause"
     * state scopeDue() guards against ever actually billing. Not a bug by
     * itself: RecurringInvoiceController::toggle() deliberately allows
     * reactivating a template in this state (e.g. to regenerate a
     * corrected invoice, or catch up on unpaid historical billing) and
     * scopeDue() makes that safe — the unattended cron can never pick it
     * back up and double-bill. Nothing currently calls this to auto-pause
     * anything (no scheduled self-heal exists); it's a diagnostic helper
     * only, exercised by its own test. If a "stale contracts" report or
     * similar is ever built, this is the check to use.
     */
    public function isStaleActive(): bool
    {
        return $this->is_active
            && $this->end_date !== null
            && $this->next_run_on->gt($this->end_date);
    }

    /**
     * This template's own per-cycle amount — pre-GST, what one invoice
     * generated from this template actually bills (same formula
     * GenerateRecurringInvoices uses). Extracted from monthlyEquivalentValue()
     * below so a caller that wants the real, un-normalized cycle figure —
     * e.g. Customer::recurringValueByFrequency() — has a single source of
     * truth too, instead of re-deriving it. Requires items to already be
     * loaded (avoids an N+1 when called in a loop over many templates).
     */
    public function cycleAmount(): int
    {
        $cycleAmount = (int) $this->items->sum(fn (RecurringInvoiceItem $item) => (int) round(((float) $item->quantity) * (int) $item->rate));

        return max(0, $cycleAmount - (int) $this->discount);
    }

    /**
     * This template's own monthly-equivalent value — pre-GST, same formula
     * as GenerateRecurringInvoices' actual billing amount. Single source of
     * truth: Customer::monthlyRecurringValue() and
     * BusinessOverviewMetrics::mrrSnapshot() both call this per-template
     * instead of re-deriving the formula, so they can never quietly drift
     * apart. Requires items to already be loaded (avoids an N+1 when called
     * in a loop over many templates).
     */
    public function monthlyEquivalentValue(): int
    {
        return (int) round($this->cycleAmount() / $this->frequency->cycleMonths());
    }

    /**
     * Active templates with a fixed end_date landing within the next $days
     * days — the Contract & Renewal Dashboard's due-soon window. Templates
     * with no end_date (open-ended/ongoing) never need a renewal decision,
     * so they're excluded rather than shown with a blank date.
     */
    public function scopeRenewingWithin(Builder $query, int $days): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now()->startOfDay(), now()->addDays($days)->endOfDay()]);
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
     * True when billing was attempted for this template (at least one
     * invoice was ever created — usually a single-cycle template whose date
     * range spans exactly one billing period, auto-deactivated by
     * generateNow() once its one cycle is exhausted) but nothing survives
     * today because that invoice was later deleted, and the template was
     * never reactivated. Distinct from a template a human paused via the
     * Pause button *before* ever billing anything — that's a legitimate
     * on-hold state, not an orphan. Client profile page only; the client
     * portal already excludes anything with is_active=false.
     */
    public function isOrphaned(): bool
    {
        return ! $this->is_active
            && $this->invoices()->withTrashed()->exists()
            && ! $this->invoices()->exists();
    }

    /**
     * Client-facing "where are things with this service" status for the
     * Services tab — time + payment based, distinct from is_active (which
     * only governs whether the system keeps auto-billing). One of:
     * 'upcoming' | 'active' | 'on_hold' | 'payment_received' |
     * 'payment_pending' | 'not_billed' | 'ended'.
     *
     * $revealPaymentStatus should be false for a viewer without invoice
     * access (e.g. Support) — a period that's over falls back to 'ended'
     * instead of exposing whether the invoice was actually paid.
     *
     * 'not_billed' (added 2026-07-24) is distinct from 'ended': a period
     * whose end_date has passed but that never generated a single invoice
     * (not even one later deleted — see isOrphaned() for that case) is
     * often a deliberate historical record (e.g. logging a past service
     * period for reporting) rather than a completed billing cycle, so it
     * must never claim "Ended" as if billing happened and finished.
     */
    public function dashboardStatus(bool $revealPaymentStatus = true): string
    {
        if ($this->start_date->isFuture()) {
            return 'upcoming';
        }

        $periodOver = $this->end_date !== null && $this->end_date->isPast();

        if (! $periodOver) {
            if ($this->is_active) {
                return 'active';
            }

            // is_active=false while the period is still ongoing has two very
            // different causes: a human deliberately paused it (real
            // On Hold), or generateNow()/GenerateRecurringInvoices already
            // billed this cycle and auto-deactivated the template because
            // its schedule ran past end_date (nothing wrong — same
            // "naturally exhausted vs. paused" distinction as isStaleActive()/
            // hasEnded()). Only the former is really "On Hold" — the latter
            // just means billing is done for a period that's still current,
            // which must still read as Active, not paused.
            $naturallyExhausted = $this->end_date !== null
                && $this->next_run_on !== null
                && $this->next_run_on->gt($this->end_date);

            return $naturallyExhausted ? 'active' : 'on_hold';
        }

        if (! $revealPaymentStatus) {
            return 'ended';
        }

        $latestInvoice = $this->invoices->sortByDesc('issue_date')->first();

        if (! $latestInvoice) {
            return 'not_billed';
        }

        return $latestInvoice->status === InvoiceStatus::Paid ? 'payment_received' : 'payment_pending';
    }
}
