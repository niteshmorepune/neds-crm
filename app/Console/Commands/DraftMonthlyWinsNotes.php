<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Jobs\DraftMonthlyWinsNote;
use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DraftMonthlyWinsNotes extends Command
{
    protected $signature = 'app:draft-monthly-wins-notes
                            {--month= : Target month in Y-m format (e.g. 2026-06). Defaults to the month that just ended.}';

    protected $description = 'Queue AI-drafted "monthly wins" client notes for active, owned clients (run on the 1st of each month).';

    public function handle(): int
    {
        $monthArg = $this->option('month');
        $monthDate = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $monthKey = $monthDate->format('Y-m');

        $customers = Customer::query()
            ->where('status', CustomerStatus::Active)
            ->whereNotNull('owner_id')
            ->get();

        if ($customers->isEmpty()) {
            $this->info('No active, owned clients.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            $alreadyDrafted = Activity::where('subject_type', Customer::class)
                ->where('subject_id', $customer->id)
                ->where('event', DraftMonthlyWinsNote::ACTIVITY_EVENT)
                ->whereJsonContains('changes->month', $monthKey)
                ->exists();

            if ($alreadyDrafted) {
                $skipped++;

                continue;
            }

            DraftMonthlyWinsNote::dispatch($customer->id, $monthKey);
            $dispatched++;
        }

        $this->info("Done for {$monthDate->format('F Y')} — {$dispatched} dispatched, {$skipped} already drafted.");

        return self::SUCCESS;
    }
}
