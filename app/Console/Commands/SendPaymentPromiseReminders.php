<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\PaymentPromiseBroken;
use Illuminate\Console\Command;

class SendPaymentPromiseReminders extends Command
{
    protected $signature = 'app:send-payment-promise-reminders';

    protected $description = 'Notify accounts staff when a client-promised payment date has passed unpaid (run daily).';

    public function handle(): int
    {
        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Accounts, UserRole::Admin, UserRole::Manager)
            ->get();

        $sent = 0;

        Invoice::whereNotNull('payment_promised_date')
            ->whereColumn('amount_paid', '<', 'total')
            ->with('customer')
            ->get()
            ->each(function (Invoice $invoice) use ($recipients, &$sent) {
                if (! $invoice->promiseBroken()) {
                    return;
                }

                // Already notified for this exact promised date — wait for a new
                // promise (or a re-run once the date changes) before nagging again.
                if ($invoice->payment_promise_notified_for?->equalTo($invoice->payment_promised_date)) {
                    return;
                }

                $notification = new PaymentPromiseBroken($invoice);
                foreach ($recipients as $user) {
                    $user->notify($notification);
                }

                $invoice->update(['payment_promise_notified_for' => $invoice->payment_promised_date]);

                $this->line("Notified {$recipients->count()} staff — {$invoice->customer?->company_name} broke its payment promise");
                $sent++;
            });

        $this->info("Sent {$sent} payment promise broken reminder(s).");

        return self::SUCCESS;
    }
}
