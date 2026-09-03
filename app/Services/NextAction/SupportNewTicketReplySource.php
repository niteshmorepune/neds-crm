<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\Ticket;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 5 of the Next Action Engine: the Support counterpart of
 * SalesNewLeadCallSource — the moment an Open ticket lands in a Support
 * agent's queue with no staff reply yet, prompt them to respond, oldest
 * first. "No staff reply yet" means no TicketReply with a real user_id —
 * a ticket the customer has already followed up on again (still no staff
 * reply) still counts, since that's if anything more urgent, not less.
 * Scoped to Open specifically (not the broader Ticket::scopeOpen(), which
 * also covers InProgress/Waiting) — those statuses mean someone has
 * already engaged, so they're not "new" in the sense this prompt means.
 */
class SupportNewTicketReplySource implements NextActionSource
{
    public function key(): string
    {
        return 'support_new_ticket_reply';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Support)) {
            return null;
        }

        $snoozedTicketIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Ticket::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $ticket = Ticket::where('assignee_id', $user->id)
            ->where('status', TicketStatus::Open)
            ->whereDoesntHave('replies', fn ($q) => $q->whereNotNull('user_id'))
            ->whereNotIn('id', $snoozedTicketIds)
            ->with('customer')
            ->oldest()
            ->first();

        if ($ticket === null) {
            return null;
        }

        $client = $ticket->customer?->company_name ?? 'a client';

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Ticket::class,
            subjectId: $ticket->id,
            title: "Respond to: {$ticket->subject}",
            body: "New ticket from {$client}, opened {$ticket->created_at->diffForHumans()} — no reply yet.",
            actionUrl: route('tickets.show', $ticket),
            actionLabel: 'Open ticket',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the ticket's
     * own page), so the banner never renders a button for it and this
     * should never be reachable — throwing surfaces a wiring bug loudly
     * instead of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
