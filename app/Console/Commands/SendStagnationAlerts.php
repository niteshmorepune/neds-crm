<?php

namespace App\Console\Commands;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Mail\StagnationAlert;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendStagnationAlerts extends Command
{
    protected $signature = 'app:send-stagnation-alerts
                            {--lead-days=7 : Days without any touch before a lead is considered stagnant}
                            {--deal-days=10 : Days without any touch before a deal is considered stagnant}';

    protected $description = 'Alert owners when leads or deals have had no activity for N days (run daily at 10:00 IST).';

    public function handle(): int
    {
        $leadDays = (int) $this->option('lead-days');
        $dealDays = (int) $this->option('deal-days');

        $leadCutoff = now()->subDays($leadDays);
        $dealCutoff = now()->subDays($dealDays);

        $openLeadStatuses = [LeadStatus::New->value, LeadStatus::Contacted->value, LeadStatus::Qualified->value];
        $closedDealStages = [DealStage::Won->value, DealStage::Lost->value];

        // Leads with no activity, note, or call in the last $leadDays days.
        $stagnantLeads = Lead::query()
            ->whereIn('status', $openLeadStatuses)
            ->whereNotNull('owner_id')
            ->where('leads.created_at', '<', $leadCutoff)
            ->whereDoesntHave('activities', fn ($q) => $q->where('activities.created_at', '>', $leadCutoff))
            ->whereDoesntHave('notes', fn ($q) => $q->where('notes.created_at', '>', $leadCutoff))
            ->whereDoesntHave('callLogs', fn ($q) => $q->where('call_logs.called_at', '>', $leadCutoff))
            ->with('owner')
            ->get()
            ->groupBy('owner_id');

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

        if ($ownerIds->isEmpty()) {
            $this->info('No stagnant leads or deals.');

            return self::SUCCESS;
        }

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

        $this->info("Sent stagnation alerts to {$sent} owner(s).");

        return self::SUCCESS;
    }
}
