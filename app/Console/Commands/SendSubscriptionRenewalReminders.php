<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\SubscriptionRenewalReminder;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionRenewalDueSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionRenewalReminders extends Command
{
    protected $signature = 'app:send-subscription-renewal-reminders';

    protected $description = 'Email + bell-notify admins when a tracked subscription is due to renew soon (run daily).';

    public function handle(): int
    {
        $admins = User::where('is_active', true)->withAnyRole(UserRole::Admin)->get();

        if ($admins->isEmpty()) {
            $this->info('No active admin users to notify.');

            return self::SUCCESS;
        }

        $subscriptions = Subscription::active()->get();
        $sent = 0;

        foreach ($subscriptions as $subscription) {
            // If the renewal date has already passed, it auto-renewed — roll
            // forward before evaluating the reminder window, so renewal_date
            // always reflects the next upcoming charge.
            $subscription->rollToNextCycleIfPast();

            if (! $subscription->isDueForReminder()) {
                continue;
            }

            $notification = new SubscriptionRenewalDueSoon($subscription);
            foreach ($admins as $admin) {
                $admin->notify($notification);
                Mail::to($admin)->send(new SubscriptionRenewalReminder($admin, $subscription));
            }

            $subscription->update(['reminder_sent_for' => $subscription->renewal_date]);

            $this->line("Notified {$admins->count()} admin(s) — {$subscription->name} renews {$subscription->renewal_date->toDateString()}");
            $sent++;
        }

        $this->info("Sent {$sent} subscription renewal reminder(s).");

        return self::SUCCESS;
    }
}
