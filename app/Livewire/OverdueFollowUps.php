<?php

namespace App\Livewire;

use App\Enums\DealStage;
use App\Enums\LeadStatus;
use App\Models\CallLog;
use App\Models\Deal;
use App\Models\Lead;
use Livewire\Component;

/**
 * Dashboard widget: the current user's overdue leads, deals, and call follow-ups.
 */
class OverdueFollowUps extends Component
{
    public function render()
    {
        $user = auth()->user();
        $now = now();

        $leads = Lead::query()
            ->where('owner_id', $user->id)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', $now)
            ->whereIn('status', [LeadStatus::New->value, LeadStatus::Contacted->value, LeadStatus::Qualified->value])
            ->orderBy('next_follow_up_at')
            ->get();

        $deals = Deal::query()
            ->where('owner_id', $user->id)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', $now)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->with('customer')
            ->orderBy('next_follow_up_at')
            ->get();

        $callFollowUps = CallLog::query()
            ->where('user_id', $user->id)
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', $now)
            ->with('callable')
            ->orderBy('follow_up_at')
            ->get();

        return view('livewire.overdue-follow-ups', [
            'leads' => $leads,
            'deals' => $deals,
            'callFollowUps' => $callFollowUps,
        ]);
    }
}
