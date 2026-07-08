<?php

namespace App\Console\Commands;

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Mail\MonthlyReportReminder;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendMonthlyReportReminder extends Command
{
    protected $signature = 'app:send-monthly-report-reminder';

    protected $description = 'Email admin/manager/accounts on the last working day of the month reminding them to prepare and send monthly reports to retainer clients.';

    public function handle(): int
    {
        $tz = config('app.display_timezone');
        $today = Carbon::today($tz);

        if (! $this->isLastWorkingDayOfMonth($today)) {
            $this->info('Not the last working day of the month. Skipping.');

            return self::SUCCESS;
        }

        // Retainer clients = active customers with at least one active recurring invoice.
        $customers = Customer::where('status', CustomerStatus::Active->value)
            ->whereHas('recurringInvoices', fn ($q) => $q->where('is_active', true))
            ->with([
                'recurringInvoices' => fn ($q) => $q->where('is_active', true)->with('service'),
                'primaryContact',
            ])
            ->orderBy('company_name')
            ->get();

        if ($customers->isEmpty()) {
            $this->info('No retainer clients found. Skipping.');

            return self::SUCCESS;
        }

        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts)
            ->get();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new MonthlyReportReminder($recipient, $today, $customers));
        }

        $this->info("Monthly report reminder sent to {$recipients->count()} staff for {$customers->count()} retainer client(s).");

        return self::SUCCESS;
    }

    private function isLastWorkingDayOfMonth(Carbon $date): bool
    {
        // Walk back from end-of-month to find the last non-Sunday.
        $candidate = $date->copy()->endOfMonth()->startOfDay();
        while ($candidate->dayOfWeek === Carbon::SUNDAY) {
            $candidate->subDay();
        }

        return $date->equalTo($candidate);
    }
}
