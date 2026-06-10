<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'app:mark-overdue-invoices';

    protected $description = 'Flag sent/partially-paid invoices past their due date as overdue (run daily).';

    public function handle(): int
    {
        $count = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->whereColumn('amount_paid', '<', 'total')
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $this->info("Marked {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
