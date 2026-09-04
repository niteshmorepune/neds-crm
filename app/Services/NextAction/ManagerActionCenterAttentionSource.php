<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\ManagerActionCenterMetrics;
use App\Support\NextAction;

/**
 * Phase 9 of the Next Action Engine — Manager journey installment 1.
 * Researched first: the Approval Center is already well-covered by push
 * notifications (LeaveRequestSubmitted, WorkFromHomeRequestSubmitted,
 * QuotationNeedsApproval all fire the moment something lands there), so a
 * popup for it would be pure duplication. ManagerActionCenterMetrics'
 * six signals (overdue tasks, at-risk clients, overdue invoices,
 * team-wide SLA breaches, escalated tickets, renewals due) plus its
 * pending-follow-ups count have NO push notification at all — a Manager
 * only ever finds out by remembering to open that page. Confirmed with
 * the owner via AskUserQuestion to surface this as one aggregate nudge
 * reusing ManagerActionCenterMetrics::signals() wholesale, rather than
 * splitting into six separate one-record-at-a-time sources — unlike
 * every other source in this engine, these are counts across many
 * records, not a single item with an obvious "oldest first" order, so
 * they don't fit that per-record pattern. Resolving means visiting the
 * Action Center itself, same as this page already works today.
 */
class ManagerActionCenterAttentionSource implements NextActionSource
{
    private const SUBJECT_TYPE = 'manager_action_center';

    private const SUBJECT_ID = 1;

    public function key(): string
    {
        return 'manager_action_center_attention';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return null;
        }

        $isSnoozed = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('subject_id', self::SUBJECT_ID)
            ->where('snoozed_until', '>', now())
            ->exists();

        if ($isSnoozed) {
            return null;
        }

        $metrics = app(ManagerActionCenterMetrics::class);
        $signals = $metrics->signals()->filter(fn (array $signal) => $signal['count'] > 0);
        $followUpCount = $metrics->pendingFollowUpCount();

        $total = $signals->sum('count') + $followUpCount;

        if ($total === 0) {
            return null;
        }

        $breakdown = $signals->map(fn (array $signal) => "{$signal['count']} ".mb_strtolower($signal['label']))->values();

        if ($followUpCount > 0) {
            $breakdown->push("{$followUpCount} pending follow-up".($followUpCount === 1 ? '' : 's'));
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: self::SUBJECT_TYPE,
            subjectId: self::SUBJECT_ID,
            title: $total === 1 ? '1 item needs your attention' : "{$total} items need your attention",
            body: $breakdown->implode(', ').'.',
            actionUrl: route('manager-action-center.index'),
            actionLabel: 'Open Action Center',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the Manager
     * Action Center, which is where each of its several signal types is
     * actually resolved), so the banner never renders a button for it and
     * this should never be reachable — throwing surfaces a wiring bug
     * loudly instead of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
