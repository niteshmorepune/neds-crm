<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 10 of the Next Action Engine — originally scoped as "Telecaller
 * journey installment 2," but turned out, on investigation, to be a
 * shared gap rather than a Telecaller-specific one: CallLog.follow_up_at
 * has no role restriction at all — any user who logs a call (Sales,
 * Telecaller, Support, whoever) can set one. The existing
 * SendCallFollowUpReminders command (every 5 minutes) already fires
 * right on time and only once per follow-up (via follow_up_notified_at),
 * but CallFollowUpDue's own via() only returns ['database'] — no mail
 * channel, no bell-badge polling found anywhere — so it's a real,
 * timely trigger that nobody actually watches. This source doesn't
 * duplicate that command; it just makes the same already-correct signal
 * interrupt the user instead of sitting unread on the Notifications page.
 *
 * No role gate, matching the underlying mechanism's own lack of one.
 * whereHasMorph (rather than a plain whereNotNull/with) excludes a
 * CallLog whose Lead/Customer has since been soft-deleted, so this never
 * tries to build a broken link.
 */
class CallFollowUpDueSource implements NextActionSource
{
    public function key(): string
    {
        return 'call_follow_up_due';
    }

    public function next(User $user): ?NextAction
    {
        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', CallLog::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $callLog = CallLog::where('user_id', $user->id)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->whereNotIn('id', $snoozedIds)
            ->whereHasMorph('callable', [Lead::class, Customer::class])
            ->with('callable')
            ->oldest('follow_up_at')
            ->first();

        if ($callLog === null) {
            return null;
        }

        $callable = $callLog->callable;
        $name = $callable instanceof Lead ? $callable->name : $callable->company_name;
        $queryParam = $callable instanceof Lead ? 'lead_id' : 'customer_id';

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: CallLog::class,
            subjectId: $callLog->id,
            title: "Follow-up call due: {$name}",
            body: filled($callLog->next_action) ? $callLog->next_action : 'Due '.$callLog->follow_up_at->diffForHumans().'.',
            actionUrl: route('calls.create', [$queryParam => $callable->id]),
            actionLabel: 'Log the call',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the Log a Call
     * form), so the banner never renders a button for it and this should
     * never be reachable — throwing surfaces a wiring bug loudly instead
     * of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
