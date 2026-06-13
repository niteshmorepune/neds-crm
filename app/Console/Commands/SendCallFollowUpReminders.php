<?php

namespace App\Console\Commands;

use App\Models\CallLog;
use App\Notifications\CallFollowUpDue;
use Illuminate\Console\Command;

class SendCallFollowUpReminders extends Command
{
    protected $signature = 'app:send-call-followup-reminders';

    protected $description = 'Notify users when a call follow-up is due (run every 5 minutes via scheduler).';

    public function handle(): int
    {
        $due = CallLog::query()
            ->with(['user', 'callable'])
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->whereNull('follow_up_notified_at')
            ->get();

        $sent = 0;

        foreach ($due as $call) {
            if ($call->user === null) {
                continue;
            }

            $call->user->notify(new CallFollowUpDue($call));

            $call->follow_up_notified_at = now();
            $call->saveQuietly();

            $sent++;
        }

        $this->info("Sent {$sent} call follow-up reminder(s).");

        return self::SUCCESS;
    }
}
