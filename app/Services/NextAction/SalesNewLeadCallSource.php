<?php

namespace App\Services\NextAction;

use App\Contracts\NextActionSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Support\NextAction;

/**
 * Phase 1 of the Next Action Engine (see CLAUDE.md decisions log,
 * 2026-09-03): the moment a new lead lands on a Sales rep's own queue with
 * no call logged yet, prompt them to call it — oldest first, one at a time.
 * Gated on hasRole() (primary or additional) per the sidebar/menu-visibility
 * convention, not the dashboard-panel primary-only one, since this is an
 * access question ("does this apply to you"), not a "which single panel"
 * question.
 */
class SalesNewLeadCallSource implements NextActionSource
{
    public function key(): string
    {
        return 'sales_new_lead_call';
    }

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole(UserRole::Sales)) {
            return null;
        }

        $snoozedLeadIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Lead::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $lead = Lead::where('owner_id', $user->id)
            ->where('status', LeadStatus::New)
            ->whereDoesntHave('callLogs')
            ->whereNotIn('id', $snoozedLeadIds)
            ->oldest()
            ->first();

        if ($lead === null) {
            return null;
        }

        return new NextAction(
            sourceKey: $this->key(),
            subjectType: Lead::class,
            subjectId: $lead->id,
            title: "Call {$lead->name}",
            body: "New lead captured {$lead->created_at->diffForHumans()} — no call logged yet.",
            actionUrl: route('calls.create', ['lead_id' => $lead->id]),
            actionLabel: 'Log the call',
        );
    }
}
