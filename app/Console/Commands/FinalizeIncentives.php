<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\IncentiveStatement;
use App\Models\User;
use App\Services\IncentiveCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Locks each active Sales user's incentive numbers for the month that just
 * ended into incentive_statements, so payroll has a stable figure even if a
 * Deal is edited after month close. Everything before finalization is a live,
 * recalculated-every-view estimate (IncentiveCalculator, never stored) — this
 * command is the one place that snapshots it. Same shape and idempotency
 * pattern as DraftMonthlyWinsNotes / CreateMonthlyBriefs (updateOrCreate keyed
 * on user_id + period_start, so a re-run recomputes rather than duplicating).
 */
class FinalizeIncentives extends Command
{
    protected $signature = 'app:finalize-incentives
                            {--month= : Target month in Y-m format (e.g. 2026-06). Defaults to the month that just ended.}';

    protected $description = 'Lock each active Sales user\'s incentive statement for the month that just ended (run on the 1st of each month).';

    public function handle(IncentiveCalculator $calculator): int
    {
        $monthArg = $this->option('month');
        $monthStart = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $reps = User::query()->where('is_active', true)->withAnyRole(UserRole::Sales)->get();

        if ($reps->isEmpty()) {
            $this->info('No active Sales users.');

            return self::SUCCESS;
        }

        foreach ($reps as $rep) {
            $estimate = $calculator->estimateForUser($rep, $monthStart);

            $values = [
                'sales_value' => $estimate['sales_value'],
                'individual_incentive' => $estimate['individual_incentive'],
                'team_bonus_eligible' => $estimate['team_bonus_eligible'],
                'team_bonus_share' => $estimate['team_bonus_share'],
                'total_incentive' => $estimate['total_incentive'],
                'finalized_at' => now(),
            ];

            // Plain updateOrCreate() would match period_start with a naive
            // where(), which a 'date' cast quietly serializes as a full
            // datetime string (a grammar-format quirk, not a real datetime
            // value) — whereDate() sidesteps it, same as
            // SalesTarget::scopeForPeriod().
            $existing = IncentiveStatement::where('user_id', $rep->id)
                ->whereDate('period_start', $monthStart)
                ->first();

            if ($existing) {
                $existing->update($values);
            } else {
                IncentiveStatement::create(['user_id' => $rep->id, 'period_start' => $monthStart] + $values);
            }
        }

        $this->info("Finalized incentives for {$monthStart->format('F Y')} — {$reps->count()} rep(s).");

        return self::SUCCESS;
    }
}
