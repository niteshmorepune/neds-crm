<?php

namespace App\Console\Commands;

use App\Models\FollowUpReminder;
use App\Notifications\FollowUpReminderDue;
use Illuminate\Console\Command;

/**
 * Notifies users when their own Dashboard follow-up reminder (App\Models\
 * FollowUpReminder — set via the "Follow-up Reminder" widget on the
 * Dashboard, e.g. "call the client," "share a report") comes due.
 *
 * Deliberately a separate command from app:send-followup-reminders, which
 * predates this feature and is a daily digest of Lead/Deal next_follow_up_at
 * dates — same underlying idea, but that one runs once a day and only covers
 * Leads/Deals; this one runs every 5 minutes (matching
 * app:send-call-followup-reminders' cadence) since a Dashboard reminder is
 * set with a specific time, and can be against any client, not just a Lead
 * or Deal record.
 */
class SendDashboardFollowUpReminders extends Command
{
    protected $signature = 'app:send-dashboard-followup-reminders';

    protected $description = 'Notify users when a Dashboard follow-up reminder is due (run every 5 minutes via scheduler).';

    public function handle(): int
    {
        $due = FollowUpReminder::query()
            ->awaitingNotification()
            ->with('user')
            ->get();

        $sent = 0;

        foreach ($due as $reminder) {
            if ($reminder->user === null) {
                continue;
            }

            $reminder->user->notify(new FollowUpReminderDue($reminder));

            $reminder->notified_at = now();
            $reminder->saveQuietly();

            $sent++;
        }

        $this->info("Sent {$sent} Dashboard follow-up reminder(s).");

        return self::SUCCESS;
    }
}
