<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadEscalatedToManagerNotification;
use App\Notifications\LeadOwnerReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Speed-to-lead: contacting a brand-new lead within minutes rather than
 * hours measurably improves conversion, so a New lead nobody has engaged
 * with (no note, call log, or edit since it was created/assigned) gets its
 * owner reminded a short while after assignment, then escalated to
 * Admin/Manager if it's STILL untouched later. Runs every 5 minutes
 * (routes/console.php) so reminders land close to the actual threshold.
 *
 * "Untouched" mirrors SendStagnationAlerts' own definition (no activities/
 * notes/callLogs newer than the cutoff) at a much shorter timescale — that
 * command catches multi-day neglect, this one catches the critical first
 * hour. Guard columns (owner_reminder_sent_at/manager_escalated_at) are
 * bookkeeping only, written via forceFill()->saveQuietly() (same pattern as
 * ScoreLead) so they don't pollute the lead's activity log.
 */
class EscalateUntouchedLeads extends Command
{
    protected $signature = 'app:escalate-untouched-leads
                            {--owner-minutes=20 : Minutes since creation before reminding the owner}
                            {--manager-minutes=60 : Minutes since creation before escalating to Admin/Manager}';

    protected $description = 'Speed-to-lead: reminds an owner about an untouched new lead, then escalates to Admin/Manager if it stays untouched.';

    public function handle(): int
    {
        $this->remindOwners(now()->subMinutes((int) $this->option('owner-minutes')));
        $this->escalateToManagers(now()->subMinutes((int) $this->option('manager-minutes')));

        return self::SUCCESS;
    }

    private function untouchedQuery(Carbon $cutoff)
    {
        return Lead::query()
            ->where('status', LeadStatus::New->value)
            ->whereNotNull('owner_id')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('activities', fn ($q) => $q->where('activities.created_at', '>', $cutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('notes.created_at', '>', $cutoff))
            ->whereDoesntHave('callLogs', fn ($q) => $q->where('call_logs.called_at', '>', $cutoff));
    }

    private function remindOwners(Carbon $cutoff): void
    {
        $leads = $this->untouchedQuery($cutoff)->whereNull('owner_reminder_sent_at')->with('owner')->get();

        foreach ($leads as $lead) {
            $lead->owner?->notify(new LeadOwnerReminderNotification($lead));
            $lead->forceFill(['owner_reminder_sent_at' => now()])->saveQuietly();
        }

        if ($leads->isNotEmpty()) {
            $this->info("Reminded owners on {$leads->count()} untouched lead(s).");
        }
    }

    private function escalateToManagers(Carbon $cutoff): void
    {
        $leads = $this->untouchedQuery($cutoff)
            ->whereNotNull('owner_reminder_sent_at')
            ->whereNull('manager_escalated_at')
            ->with('owner')
            ->get();

        if ($leads->isEmpty()) {
            return;
        }

        $managers = User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        foreach ($leads as $lead) {
            $managers->each(fn (User $manager) => $manager->notify(new LeadEscalatedToManagerNotification($lead)));
            $lead->forceFill(['manager_escalated_at' => now()])->saveQuietly();
        }

        $this->info("Escalated {$leads->count()} untouched lead(s) to managers.");
    }
}
