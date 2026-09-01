<?php

namespace App\Console\Commands;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Mail\StagnationAlert;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadStagnationEscalatedNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendStagnationAlerts extends Command
{
    protected $signature = 'app:send-stagnation-alerts
                            {--lead-days=7 : Days without any touch before a lead is considered stagnant}
                            {--deal-days=10 : Days without any touch before a deal is considered stagnant}
                            {--manager-days=3 : Additional days past lead-days, with still no activity, before escalating a lead to Admin/Manager}';

    protected $description = 'Alert owners when leads or deals have had no activity for N days, and escalate a lead to Admin/Manager if it stays untouched even longer (run daily at 10:00 IST).';

    public function handle(): int
    {
        if (now(config('app.display_timezone'))->isSunday()) {
            $this->info('Sunday — skipping stagnation alerts.');

            return self::SUCCESS;
        }

        $leadDays = (int) $this->option('lead-days');
        $dealDays = (int) $this->option('deal-days');
        $managerDays = (int) $this->option('manager-days');

        $leadCutoff = now()->subDays($leadDays);
        $dealCutoff = now()->subDays($dealDays);

        $closedDealStages = [DealStage::Won->value, DealStage::Lost->value];

        // Leads with no activity, note, or call in the last $leadDays days.
        $stagnantLeads = $this->stagnantLeadsQuery($leadCutoff)->with('owner')->get()->groupBy('owner_id');

        // Deals with no activity or note in the last $dealDays days.
        $stagnantDeals = Deal::query()
            ->whereNotIn('stage', $closedDealStages)
            ->whereNotNull('owner_id')
            ->where('deals.created_at', '<', $dealCutoff)
            ->whereDoesntHave('activities', fn ($q) => $q->where('activities.created_at', '>', $dealCutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('notes.created_at', '>', $dealCutoff))
            ->with(['owner', 'customer'])
            ->get()
            ->groupBy('owner_id');

        $ownerIds = $stagnantLeads->keys()->merge($stagnantDeals->keys())->unique();

        $sent = 0;

        foreach ($ownerIds as $ownerId) {
            $leads = $stagnantLeads->get($ownerId, collect());
            $deals = $stagnantDeals->get($ownerId, collect());

            $user = ($leads->first()?->owner ?? $deals->first()?->owner);
            if ($user === null) {
                continue;
            }

            Mail::to($user)->send(new StagnationAlert($user, $leads, $deals, $leadDays, $dealDays));
            $sent++;
        }

        if ($sent > 0) {
            $this->info("Sent stagnation alerts to {$sent} owner(s).");
        } else {
            $this->info('No stagnant leads or deals.');
        }

        $this->escalateToManagers($leadCutoff->copy()->subDays($managerDays), $leadDays + $managerDays);

        return self::SUCCESS;
    }

    /**
     * @return Builder<Lead>
     */
    private function stagnantLeadsQuery(Carbon $cutoff): Builder
    {
        $openLeadStatuses = [LeadStatus::New->value, LeadStatus::Contacted->value, LeadStatus::Qualified->value];

        return Lead::query()
            ->whereIn('status', $openLeadStatuses)
            ->whereNotNull('owner_id')
            ->where('leads.created_at', '<', $cutoff)
            ->whereDoesntHave('activities', fn ($q) => $q->where('activities.created_at', '>', $cutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('notes.created_at', '>', $cutoff))
            ->whereDoesntHave('callLogs', fn ($q) => $q->where('call_logs.called_at', '>', $cutoff));
    }

    /**
     * A lead already past the owner-alert threshold and STILL untouched
     * $totalDays out escalates to every active Admin/Manager. Deliberately
     * not deduped -- re-fires daily for as long as the lead stays stagnant,
     * the same "persistent nag" behaviour as the owner's own email above,
     * rather than a one-time bookkeeping flag (contrast
     * EscalateUntouchedLeads' owner_reminder_sent_at/manager_escalated_at,
     * a one-shot speed-to-lead nudge for a different failure mode entirely).
     * Scoped to Leads only -- the spec this shipped from didn't ask for a
     * Deal-side equivalent, and Deal stagnation already has its own
     * owner-facing email above with no prior manager tier to extend.
     */
    private function escalateToManagers(Carbon $managerCutoff, int $totalDays): void
    {
        $leads = $this->stagnantLeadsQuery($managerCutoff)->get();

        if ($leads->isEmpty()) {
            return;
        }

        $managers = User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        foreach ($leads as $lead) {
            $notification = new LeadStagnationEscalatedNotification($lead, $totalDays);
            $managers->each(fn (User $manager) => $manager->notify($notification));
        }

        $this->info("Escalated {$leads->count()} stagnant lead(s) to managers.");
    }
}
