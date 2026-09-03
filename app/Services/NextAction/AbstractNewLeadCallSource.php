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
 * Shared shape for "the moment a new lead lands in your queue with no call
 * logged yet, prompt you to call it" — oldest first, one at a time. Sales
 * (owner_id) and Telecaller (telecaller_id) are the same flow against a
 * different ownership column and role, so this is a real, non-speculative
 * shared base rather than two near-duplicate ~60-line classes. Gated on
 * hasRole() (primary or additional) per the sidebar/menu-visibility
 * convention, not the dashboard-panel primary-only one, since this is an
 * access question ("does this apply to you"), not a "which single panel"
 * question — matters concretely here since real telecallers hold that role
 * as an additional role, never primary (see [[lead-visibility-telecaller-assignment]]).
 */
abstract class AbstractNewLeadCallSource implements NextActionSource
{
    abstract protected function role(): UserRole;

    /** The Lead column that owns this queue — 'owner_id' or 'telecaller_id'. */
    abstract protected function ownerColumn(): string;

    public function next(User $user): ?NextAction
    {
        if (! $user->hasRole($this->role())) {
            return null;
        }

        $snoozedLeadIds = NextActionSnooze::where('user_id', $user->id)
            ->where('source_key', $this->key())
            ->where('subject_type', Lead::class)
            ->where('snoozed_until', '>', now())
            ->pluck('subject_id');

        $lead = Lead::where($this->ownerColumn(), $user->id)
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

    /**
     * This source's prompt always sets actionUrl (a link to the Log a Call
     * form), so the banner never renders a button for it and this should
     * never be reachable — throwing surfaces a wiring bug loudly instead of
     * silently no-op'ing.
     */
    public function complete(User $user, int $subjectId): void
    {
        throw new \RuntimeException(static::class.' has no inline completion — its prompt always links out.');
    }
}
