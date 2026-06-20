<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice;
use Illuminate\Console\Command;

class FixRecurringNextRunDates extends Command
{
    protected $signature = 'app:fix-recurring-next-run-dates';

    protected $description = 'Advance any past-due next_run_on dates to the next future cycle (one-off correction, generates no invoices).';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $fixed = 0;

        RecurringInvoice::where('next_run_on', '<', $today)->get()
            ->each(function (RecurringInvoice $r) use ($today, &$fixed) {
                $next = $r->next_run_on->copy()->startOfDay();
                while ($next->lt($today)) {
                    $next = $r->frequency->advance($next);
                }
                $r->update(['next_run_on' => $next]);
                $this->line("#{$r->id} ({$r->frequency->label()}) → {$next->toDateString()}");
                $fixed++;
            });

        $this->info("Fixed {$fixed} recurring invoice(s).");

        return self::SUCCESS;
    }
}
