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

    public function convert(User $user, Lead $lead): bool
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
