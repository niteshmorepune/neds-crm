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
 * Phase 7 of the Next Action Engine — first installment of the full Sales
 * journey (lead → quotation → deal won → invoice → payment), confirmed
 * with the owner. A Quotation sitting at Sent with no client decision for
 * FOLLOW_UP_DAYS is a real, previously-unsignaled gap — nothing in this
 * app currently nudges staff about a stalled quotation (only the client
 * ever gets a decision-pending notification). No `sent_at` column exists,
 * so `updated_at` at the moment `status` last became Sent is the proxy —
 * deliberately reused rather than adding a new column for this.
 * Ownership mirrors Quotation::ownerId() exactly (deal owner if the
 * quotation came from a deal, else the customer's own owner) via query,
 * since that method itself isn't a queryable column.
 */
class QuotationFollowUpSource implements NextActionSource
{
    private const FOLLOW_UP_DAYS = 3;

    public function key(): string
    {
        return 'quotation_follow_up';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Sales)) {
            return null;
        }

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Quotation::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $quotation = Quotation::where('status', QuotationStatus::Sent)
            ->where('updated_at', '<=', now()->subDays(self::FOLLOW_UP_DAYS))
            ->whereNotIn('id', $snoozedIds)
            ->where(function ($query) use ($user) {
                $query->whereHas('deal', fn ($d) => $d->where('owner_id', $user->id))
                    ->orWhere(function ($q) use ($user) {
                        $q->whereNull('deal_id')
                            ->whereHas('customer', fn ($c) => $c->where('owner_id', $user->id));
                    });
            })
            ->with('customer')
            ->oldest('updated_at')
            ->first();

        if ($quotation === null) {
            return null;
        }

        $client = $quotation->customer?->company_name ?? 'the client';

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Quotation::class,
            subjectId: $quotation->id,
            title: "Follow up: {$client}'s quotation",
            body: "Sent {$quotation->updated_at->diffForHumans()} — no decision yet.",
            actionUrl: route('quotations.show', $quotation),
            actionLabel: 'Open quotation',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the quotation),
     * so the banner never renders a button for it and this should never be
     * reachable — throwing surfaces a wiring bug loudly instead of
     * silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
