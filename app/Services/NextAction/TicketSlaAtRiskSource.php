<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\Ticket;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 8 of the Next Action Engine — Support journey installment 1
 * (confirmed with the owner: SupportNewTicketReplySource already covers
 * "respond to a brand-new ticket"; escalation is something the assignee
 * *does*, not something to wait on; Resolved is already effectively
 * terminal with no "should be Closed" gap; satisfaction rating is purely
 * client-initiated with no existing staff-follow-up precedent to reuse —
 * confirmed via AskUserQuestion to leave those out of this installment).
 *
 * The one real gap: CheckTicketSla (hourly) only notifies Admin/Manager
 * once a ticket's SLA is already breached — the assignee who could
 * actually still make the SLA is never told at all, before or after.
 * Reuses the exact "at risk" definition already established in
 * DashboardMetrics::supportStats() and TicketController's own
 * ?at_risk=1 filter (due within 4 hours, still open) rather than
 * inventing a new threshold — this single condition also naturally
 * covers an already-breached ticket (its due time is in the past, which
 * trivially satisfies "due within 4 hours").
 */
class TicketSlaAtRiskSource implements NextActionSource
{
    private const AT_RISK_HOURS = 4;

    public function key(): string
    {
        return 'ticket_sla_at_risk';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Support)) {
            return null;
        }

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Ticket::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $ticket = Ticket::query()
            ->open()
            ->where('assignee_id', $user->id)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now()->addHours(self::AT_RISK_HOURS))
            ->whereNotIn('id', $snoozedIds)
            ->with('customer')
            ->oldest('sla_due_at')
            ->first();

        if ($ticket === null) {
            return null;
        }

        $client = $ticket->customer?->company_name ?? 'a client';
        $breached = $ticket->isSlaBreached();

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Ticket::class,
            subjectId: $ticket->id,
            title: ($breached ? 'SLA breached: ' : 'SLA due soon: ').$ticket->subject,
            body: ($breached ? 'Overdue since ' : 'Due ').$ticket->sla_due_at->diffForHumans()." — {$client} is waiting.",
            actionUrl: route('tickets.show', $ticket),
            actionLabel: 'Open ticket',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the ticket),
     * so the banner never renders a button for it and this should never
     * be reachable — throwing surfaces a wiring bug loudly instead of
     * silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
