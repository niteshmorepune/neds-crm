<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\UserRole;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\RoleTargetMetrics;
use App\Support\NextAction;
use Illuminate\Support\Carbon;

/**
 * Phase 9 of the Next Action Engine — Manager journey installment 1 (see
 * ManagerActionCenterAttentionSource for the shared context). A second,
 * unrelated real gap: RoleTargetMetrics computes target/actual/pct per
 * team member on demand for the Team Targets page, but nobody is ever
 * proactively told when someone is genuinely falling behind mid-month —
 * it's a pure "go look" report today.
 *
 * "Behind" is judged relative to how much of the month has elapsed, not
 * a fixed percentage — a raw target% comparison would falsely flag
 * everyone in the first few days of any month. Two guards keep this from
 * being noisy: MIN_DAY_OF_MONTH skips the whole first week (too little
 * data to mean anything yet), and BEHIND_BUFFER_POINTS requires a real,
 * meaningfully-large gap (not just a percentage point or two of normal
 * day-to-day variance) before flagging. Only the 4 roles
 * TargetMetric::forRole() actually maps (Support/Accounts/Intern/
 * Telecaller) have a KRA target at all — Sales keeps its own separate
 * SalesTarget mechanism, untouched here, matching every other place in
 * this app that draws that same boundary.
 */
class TeamMemberBehindTargetSource implements NextActionSource
{
    private const MIN_DAY_OF_MONTH = 7;

    private const BEHIND_BUFFER_POINTS = 25;

    /** @var array<int, UserRole> */
    private const TARGET_ROLES = [UserRole::Support, UserRole::Accounts, UserRole::Intern, UserRole::Telecaller];

    public function key(): string
    {
        return 'team_member_behind_target';
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

        $metrics = app(RoleTargetMetrics::class);
        $worstMember = null;
        $worstRow = null;
        $worstDelta = null;

        foreach (self::TARGET_ROLES as $role) {
            foreach ($metrics->teamRows($role)['rows'] as $row) {
                if ($row['target'] === null || $row['pct'] === null) {
                    continue;
                }

                if ($snoozedIds->contains($row['user']->id)) {
                    continue;
                }

                $delta = $row['pct'] - $monthElapsedPct;

                if ($delta < -self::BEHIND_BUFFER_POINTS && ($worstDelta === null || $delta < $worstDelta)) {
                    $worstMember = $row['user'];
                    $worstRow = $row;
                    $worstDelta = $delta;
                }
            }
        }

        if ($worstMember === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: User::class,
            subjectId: $worstMember->id,
            title: "Check in with {$worstMember->name}",
            body: "{$worstRow['pct']}% of this month's target so far, with ".round($monthElapsedPct).'% of the month gone.',
            actionUrl: route('employees.show', $worstMember),
            actionLabel: 'Open profile',
        );
    }

    /**
     * This source's prompt always sets actionUrl (a link to the team
     * member's Employee 360° profile), so the banner never renders a
     * button for it and this should never be reachable — throwing
     * surfaces a wiring bug loudly instead of silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(self::class.' has no inline completion — its prompt always links out.');
    }
}
