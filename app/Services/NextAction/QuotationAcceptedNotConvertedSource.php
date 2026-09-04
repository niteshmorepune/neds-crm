<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\Quotation;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 11 of the Next Action Engine — Accounts journey installment 1 (see
 * DraftInvoiceUnsentSource for shared context). "Convert to invoice" on an
 * Accepted quotation is entirely manual today — nothing anywhere checks
 * for an Accepted quotation with no linked invoice N days later. It's
 * Sales' click, but the downstream effect (agreed revenue never billed)
 * is Accounts' problem, so this is gated the same as the rest of this
 * installment (Accounts + Admin/Manager) rather than only the quotation
 * owner. No `accepted_at` column exists (only `approved_at`, a different,
 * internal-approval concept), so `updated_at` at the moment status last
 * became Accepted is the reused proxy — same convention
 * QuotationFollowUpSource already established for the identical gap on
 * the Sent status.
 */
class QuotationAcceptedNotConvertedSource implements NextActionSource
{
    private const STALE_DAYS = 3;

    public function key(): string
    {
        return 'quotation_accepted_not_converted';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Accounts, UserRole::Admin, UserRole::Manager)) {
            return null;
        }

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Quotation::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $quotation = Quotation::where('status', QuotationStatus::Accepted)
            ->where('updated_at', '<=', now()->subDays(self::STALE_DAYS))
            ->whereDoesntHave('invoice')
            ->whereNotIn('id', $snoozedIds)
            ->with('customer')
            ->oldest('updated_at')
            ->first();

        if ($quotation === null) {
            return null;
        }

        $client = $quotation->customer?->company_name ?? 'a client';

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Quotation::class,
            subjectId: $quotation->id,
            title: "Convert accepted quotation: {$client}",
            body: 'Accepted '.$quotation->updated_at->diffForHumans().' — no invoice raised yet.',
            actionUrl: route('quotations.show', $quotation),
            actionLabel: 'Open quotation',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the quotation,
     * where Convert to Invoice is a real button with its own checks — not
     * something to duplicate as a one-click action here), so the banner
     * never renders a button for it and this should never be reachable —
     * throwing surfaces a wiring bug loudly instead of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
