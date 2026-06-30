<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\RecurringInvoiceDueSoon;
use Illuminate\Console\Command;

class SendRecurringInvoiceDueWarnings extends Command
{
    protected $signature = 'app:send-recurring-invoice-due-warnings';

    protected $description = 'Notify accounts staff 7 days before a recurring-linked invoice is due.';

    public function handle(): int
    {
        $dueOn = now()->timezone('Asia/Kolkata')->addDays(7)->toDateString();

        $invoices = Invoice::whereNotNull('recurring_invoice_id')
            ->whereDate('due_date', $dueOn)
            ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value])
            ->with('customer')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No recurring invoices due in 7 days.');

            return self::SUCCESS;
        }

        $recipients = User::where('is_active', true)
            ->whereIn('role', [UserRole::Accounts->value, UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        $sent = 0;
        foreach ($invoices as $invoice) {
            $notification = new RecurringInvoiceDueSoon($invoice);
            foreach ($recipients as $user) {
                $user->notify($notification);
            }
            $this->line("Notified {$recipients->count()} staff — {$invoice->customer?->company_name} due {$dueOn}");
            $sent++;
        }

        $this->info("Sent {$sent} recurring invoice due warning(s).");

        return self::SUCCESS;
    }
}
