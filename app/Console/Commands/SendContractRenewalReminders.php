<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Notifications\ContractRenewalDueSoon;
use Illuminate\Console\Command;

class SendContractRenewalReminders extends Command
{
    protected $signature = 'app:send-contract-renewal-reminders';

    protected $description = 'Notify accounts/admin/manager + the owning sales rep when an active recurring contract ends within 30 days.';

    public function handle(): int
    {
        $windowEnd = now()->addDays(30)->toDateString();
        $today = now()->toDateString();

        $templates = RecurringInvoice::query()
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $windowEnd)
            ->where(function ($q) {
                $q->whereNull('renewal_reminder_sent_for')
                    ->orWhereColumn('renewal_reminder_sent_for', '!=', 'end_date');
            })
            ->with(['customer', 'service'])
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No contracts renewing within 30 days.');

            return self::SUCCESS;
        }

        $staffRecipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Accounts, UserRole::Admin, UserRole::Manager)
            ->get();

        $sent = 0;
        foreach ($templates as $template) {
            $notification = new ContractRenewalDueSoon($template);
            $recipients = $staffRecipients;

            $ownerId = $template->customer?->owner_id;
            if ($ownerId && ! $recipients->contains('id', $ownerId)) {
                $owner = User::find($ownerId);
                if ($owner) {
                    $recipients = $recipients->push($owner);
                }
            }

            $recipients->each(fn (User $u) => $u->notify($notification));
            $template->update(['renewal_reminder_sent_for' => $template->end_date]);

            $this->line("Notified {$recipients->count()} — {$template->customer?->company_name} ends {$template->end_date->toDateString()}");
            $sent++;
        }

        $this->info("Sent {$sent} contract renewal reminder(s).");

        return self::SUCCESS;
    }
}
