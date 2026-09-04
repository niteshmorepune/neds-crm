<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\SalesPipelineMetrics;
use App\Support\NextAction;
use Illuminate\Support\Carbon;

/**
 * Sales' own version of TeamMemberBehindTargetSource — Pipeline Playbook
 * gap idea #1 (2026-09-04). Support/Accounts/Intern/Telecaller already get
 * a proactive "check in with someone falling behind" nudge via
 * RoleTargetMetrics, but Sales was explicitly excluded there since it runs
 * on its own separate SalesTarget/SalesPipelineMetrics mechanism — this
 * source is that same nudge, built on repLeaderboard()'s target_pct
 * instead. Same "behind relative to how much of the month has elapsed"
 * math and the same MIN_DAY_OF_MONTH/BEHIND_BUFFER_POINTS thresholds as
 * the original, confirmed via AskUserQuestion rather than assuming Sales
 * needs different sensitivity just because deal sizes are lumpier.
 *
 * Checked BEFORE TeamMemberBehindTargetSource in NextActionEngine's list
 * (confirmed via AskUserQuestion) — a rep missing their revenue number is
 * treated as the bigger business signal when both would otherwise fire the
 * same day.
 */
class SalesRepBehindTargetSource implements NextActionSource
{
    private const MIN_DAY_OF_MONTH = 7;

    private const BEHIND_BUFFER_POINTS = 25;

    public function key(): string
    {
        return 'sales_rep_behind_target';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return null;
        }

        $now = Carbon::now(config('app.display_timezone'));

        if ($now->day < self::MIN_DAY_OF_MONTH) {
            return null;
        }

        $monthElapsedPct = $now->day / $now->daysInMonth * 100;

        $snoozedIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', User::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $worstRep = null;
        $worstPct = null;
        $worstDelta = null;

        foreach (app(SalesPipelineMetrics::class)->repLeaderboard() as $row) {
            if ($row['target_pct'] === null) {
                continue;
            }

            if ($snoozedIds->contains($row['user']->id)) {
                continue;
            }

            $delta = $row['target_pct'] - $monthElapsedPct;

            if ($delta < -self::BEHIND_BUFFER_POINTS && ($worstDelta === null || $delta < $worstDelta)) {
                $worstRep = $row['user'];
                $worstPct = $row['target_pct'];
                $worstDelta = $delta;
            }
        }

        if ($worstRep === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: User::class,
            subjectId: $worstRep->id,
            title: "Check in with {$worstRep->name}",
            body: "{$worstPct}% of this month's sales target so far, with ".round($monthElapsedPct).'% of the month gone.',
            actionUrl: route('employees.show', $worstRep),
            actionLabel: 'Open profile',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the rep's
     * Employee 360° profile), so the banner never renders a button for it
     * and this should never be reachable — throwing surfaces a wiring bug
     * loudly instead of silently no-op'ing. Mirrors
     * TeamMemberBehindTargetSource::complete().
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
