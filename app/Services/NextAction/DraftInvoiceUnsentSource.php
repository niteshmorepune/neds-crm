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
 * Phase 11 of the Next Action Engine — Accounts journey installment 1.
 * Researched first, same discipline as every prior installment: found two
 * real, zero-coverage gaps (this one and QuotationAcceptedNotConvertedSource)
 * and ruled out two others (missing GSTIN is a data-quality list, not a
 * single-record "call it now" prompt; TDS is already reconciled
 * synchronously at payment-recording time, no unmet concept exists).
 *
 * A Draft invoice today comes almost entirely from the SMDost webhook
 * (SmdostWebhookController::briefApproved(), a ₹0 placeholder for Accounts
 * to price and send) — it fires exactly one database notification
 * (SmdostBriefApproved) at creation time to Accounts+Admin, then nothing
 * ever re-nudges if that gets missed or dismissed. Threshold and audience
 * (Accounts + Admin/Manager, wider than the original notification's own
 * Accounts+Admin) confirmed with the owner via AskUserQuestion.
 */
class DraftInvoiceUnsentSource implements NextActionSource
{
    private const STALE_DAYS = 3;

    public function key(): string
    {
        return 'draft_invoice_unsent';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Accounts, UserRole::Admin, UserRole::Manager)) {
            return null;
        }

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Invoice::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $invoice = Invoice::where('status', InvoiceStatus::Draft)
            ->where('created_at', '<=', now()->subDays(self::STALE_DAYS))
            ->whereNotIn('id', $snoozedIds)
            ->with('customer')
            ->oldest()
            ->first();

        if ($invoice === null) {
            return null;
        }

        $client = $invoice->customer?->company_name ?? 'a client';

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Invoice::class,
            subjectId: $invoice->id,
            title: "Price & send draft invoice: {$client}",
            body: 'Created '.$invoice->created_at->diffForHumans().' — still sitting as Draft.',
            actionUrl: route('invoices.show', $invoice),
            actionLabel: 'Open invoice',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the invoice,
     * which needs real pricing input before it can be sent — not a
     * one-click action), so the banner never renders a button for it and
     * this should never be reachable — throwing surfaces a wiring bug
     * loudly instead of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
