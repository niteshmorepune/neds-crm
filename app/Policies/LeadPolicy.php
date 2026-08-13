<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

/**
 * All authenticated users with lead-generation menu access can view any lead.
 * Create is limited to admin, manager, and sales. Delete to admin/manager.
 * Update: admin/manager any lead, Sales their own, Telecaller any lead —
 * Telecaller works a shared calling queue (no ownership/auto-assignment of
 * their own, per the 2026-07-26 decision), so they need to log outcomes and
 * move status on whichever lead needs calling, not just ones with no owner
 * (in practice nearly every lead has a Sales owner within moments of
 * creation via LeadObserver::autoAssign(), so an "unowned only" restriction
 * would leave them almost nothing to update).
 * Keep in sync with Lead::scopeVisibleTo.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Telecaller)
            || ($user->hasRole(UserRole::Sales) && $lead->owner_id === $user->id);
    }

    /**
     * Whether this user can reach the "Reassign" action on this lead at all.
     * Admin/Manager can reassign any lead to anyone (validated by
     * LeadReassignRequest). Sales can only reassign a lead they currently
     * own, and only to another active Sales peer (also enforced in
     * LeadReassignRequest, since the "who can they hand off TO" restriction
     * depends on the target, not just this lead).
     */
    public function reassign(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager)
            || ($user->hasRole(UserRole::Sales) && $lead->owner_id === $user->id);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    /**
     * Merge two duplicate leads into one — same role set as convert(), since
     * both are lead-lifecycle actions with real, hard-to-reverse consequences
     * (one lead ends up soft-deleted). Deliberately a class-level ability
     * (Lead::class, not a specific instance) since the check happens before
     * either lead in the pair is definitively chosen as primary/duplicate.
     */
    public function merge(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    /**
     * Create/import Google Meet notes on this lead — deliberately its own
     * check, not update() (owning-Sales-only editing). Mirrors CustomerPolicy::
     * manageMeetings() and Calling's menu-access role set: Admin/Manager/
     * Sales/Support, no ownership restriction — any of these roles can
     * schedule a meeting or log notes for any lead through the shared
     * company Google connection, not just the owning rep.
     */
    public function manageMeetings(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales, UserRole::Support);
    }
}
