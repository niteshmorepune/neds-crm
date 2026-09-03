<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 7 of the Next Action Engine (Sales journey installment 1, see
 * QuotationFollowUpSource for shared context). Unlike the other two
 * sources in this installment, overdue invoices already have real
 * automation — MarkOverdueInvoices flips the status, SendPaymentReminders
 * emails the client, and SendPaymentPromiseReminders notifies Accounts/
 * Admin/Manager when a promised date is broken. This source is
 * deliberately a different, complementary signal: a personal nudge to the
 * Sales rep who owns the *relationship* (Invoice::ownerId() — the
 * customer's own owner), not a collections escalation — reuses the
 * already-correct `Overdue` status (maintained by MarkOverdueInvoices)
 * rather than recomputing overdue-ness itself.
 */
class OverdueInvoiceFollowUpSource implements NextActionSource
{
    public function key(): string
    {
        return 'overdue_invoice_follow_up';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Sales)) {
            return null;
        }

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Invoice::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $invoice = Invoice::where('status', InvoiceStatus::Overdue)
            ->whereHas('customer', fn ($q) => $q->where('owner_id', $user->id))
            ->whereNotIn('id', $snoozedIds)
            ->with('customer')
            ->oldest('due_date')
            ->first();

        if ($invoice === null) {
            return null;
        }

        $client = $invoice->customer?->company_name ?? 'a client';

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Invoice::class,
            subjectId: $invoice->id,
            title: "Follow up: {$client}'s invoice is overdue",
            body: "{$invoice->invoice_number} was due {$invoice->due_date->diffForHumans()} — a personal nudge might help.",
            actionUrl: route('invoices.show', $invoice),
            actionLabel: 'Open invoice',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the invoice),
     * so the banner never renders a button for it and this should never be
     * reachable — throwing surfaces a wiring bug loudly instead of
     * silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
