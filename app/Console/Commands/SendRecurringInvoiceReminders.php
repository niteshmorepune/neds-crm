<?php

namespace App\Console\Commands;

use App\Mail\RecurringInvoiceReminder;
use App\Models\RecurringInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendRecurringInvoiceReminders extends Command
{
    protected $signature = 'app:send-recurring-invoice-reminders';

    protected $description = 'Email clients 7, 5, 3, and 1 day(s) before their next recurring invoice date.';

    /** Days before billing on which to send a reminder. */
    private const REMINDER_DAYS = [7, 5, 3, 1];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        RecurringInvoice::where('is_active', true)
            ->whereDate('next_run_on', '>', $today->toDateString())
            ->with(['customer', 'service', 'items'])
            ->get()
            ->each(function (RecurringInvoice $r) use ($today, &$sent): void {
                $daysUntil = (int) $today->diffInDays($r->next_run_on->copy()->startOfDay());

                if (! in_array($daysUntil, self::REMINDER_DAYS)) {
                    return;
                }

                // Skip if we already sent a reminder today (handles cron re-runs).
                if ($r->last_reminder_sent_at?->isToday()) {
                    return;
                }

                $email = $r->customer?->billingEmail();
                if (! $email) {
                    return;
                }

                Mail::to($email)->send(new RecurringInvoiceReminder($r, $daysUntil));
                $r->update(['last_reminder_sent_at' => $today->toDateString()]);

                $this->line("Sent {$daysUntil}-day reminder for #{$r->id} ({$r->customer?->company_name}) → {$email}");
                $sent++;
            });

        $this->info("Sent {$sent} recurring invoice reminder(s).");

        return self::SUCCESS;
    }
}
