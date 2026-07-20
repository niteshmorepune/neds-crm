<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Mail\DailyReportReminder;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendDailyReportReminders extends Command
{
    protected $signature = 'app:send-daily-report-reminders';

    protected $description = 'Email staff who have not submitted today\'s daily report (run at 18:00 IST).';

    public function handle(): int
    {
        $today = Carbon::today(config('app.display_timezone'));

        if ($today->isSunday()) {
            $this->info('Sunday — skipping daily report reminders.');

            return self::SUCCESS;
        }

        $submittedUserIds = DailyReport::whereDate('date', $today)->pluck('user_id');

        $onLeaveUserIds = Attendance::whereDate('date', $today)
            ->where('status', AttendanceStatus::Leave)
            ->pluck('user_id');

        $pending = User::query()
            ->whereNotIn('id', $submittedUserIds)
            ->whereNotIn('id', $onLeaveUserIds)
            ->get();

        foreach ($pending as $user) {
            Mail::to($user)->send(new DailyReportReminder($user));
        }

        $this->info("Reminded {$pending->count()} user(s) to submit a daily report.");

        return self::SUCCESS;
    }
}
