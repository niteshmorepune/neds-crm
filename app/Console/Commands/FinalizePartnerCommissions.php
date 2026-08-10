<?php

namespace App\Console\Commands;

use App\Models\Partner;
use App\Models\PartnerCommissionStatement;
use App\Services\PartnerCommissionCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Locks each commission-eligible partner's numbers for the month that just
 * ended into partner_commission_statements, so the ledger has a stable
 * figure even if a Deal is edited after month close. Mirrors
 * App\Console\Commands\FinalizeIncentives exactly — same idempotency
 * pattern (update-or-create keyed on partner_id + period_start via
 * whereDate(), not a naive updateOrCreate(), for the same date-cast
 * serialization reason documented there).
 */
class FinalizePartnerCommissions extends Command
{
    protected $signature = 'app:finalize-partner-commissions
                            {--month= : Target month in Y-m format (e.g. 2026-06). Defaults to the month that just ended.}';

    protected $description = 'Lock each commission-eligible partner\'s commission statement for the month that just ended (run on the 1st of each month).';

    public function handle(PartnerCommissionCalculator $calculator): int
    {
        $monthArg = $this->option('month');
        $monthStart = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $partners = Partner::query()
            ->whereNotNull('commission_rate')
            ->where('commission_rate', '>', 0)
            ->get();

        if ($partners->isEmpty()) {
            $this->info('No commission-eligible partners.');

            return self::SUCCESS;
        }

        foreach ($partners as $partner) {
            $estimate = $calculator->estimateForPartner($partner, $monthStart);

            $values = [
                'referred_value' => $estimate['referred_value'],
                'commission_rate' => $estimate['commission_rate'],
                'commission_amount' => $estimate['commission_amount'],
                'finalized_at' => now(),
            ];

            $existing = PartnerCommissionStatement::where('partner_id', $partner->id)
                ->whereDate('period_start', $monthStart)
                ->first();

            if ($existing) {
                $existing->update($values);
            } else {
                PartnerCommissionStatement::create(['partner_id' => $partner->id, 'period_start' => $monthStart] + $values);
            }
        }

        $this->info("Finalized partner commissions for {$monthStart->format('F Y')} — {$partners->count()} partner(s).");

        return self::SUCCESS;
    }
}
