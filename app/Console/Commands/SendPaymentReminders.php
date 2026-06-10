<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\PaymentReminder;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminders extends Command
{
    protected $signature = 'app:send-payment-reminders';

    protected $description = 'Email payment reminders: 3 days before due, on the due date, and every 7 days after (run daily).';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Overdue->value])
            ->whereNotNull('due_date')
            ->whereColumn('amount_paid', '<', 'total')
            ->with('customer.primaryContact')
            ->get()
            ->each(function (Invoice $invoice) use ($today, &$sent) {
                if (! $this->isReminderDue($invoice->due_date->copy()->startOfDay(), $today)) {
                    return;
                }

                if ($email = $invoice->customer->billingEmail()) {
                    Mail::to($email)->send(new PaymentReminder($invoice));
                    $sent++;
                }
            });

        $this->info("Sent {$sent} payment reminder(s).");

        return self::SUCCESS;
    }

    private function isReminderDue(Carbon $due, Carbon $today): bool
    {
        if ($today->equalTo($due->copy()->subDays(3))) {
            return true; // 3 days before
        }
        if ($today->equalTo($due)) {
            return true; // on the due date
        }

        // Every 7 days after the due date.
        return $today->greaterThan($due) && $due->diffInDays($today) % 7 === 0;
    }
}
