<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

/**
 * Admin/Manager see and can update any lead. Sales sees/updates their own
 * (or unowned) leads only. Telecaller sees/updates only leads assigned to
 * them via telecaller_id, their own separate assignment field independent
 * of owner_id (real incident 2026-09-03: Sales reps could see each other's
 * leads; the same day, Telecaller's shared-no-ownership calling queue from
 * the 2026-07-26 decision was also reversed to per-telecaller assignment —
 * see LeadObserver::autoAssignTelecaller()). Any other role reaching this
 * page (e.g. via a per-user Menu Controller override) keeps full access,
 * unaffected by this change — a multi-role user's access only ever widens.
 * Create is limited to admin, manager, and sales. Delete to admin/manager.
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
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        if (! $user->hasRole(UserRole::Sales, UserRole::Telecaller)) {
            return true;
        }

        return ($user->hasRole(UserRole::Sales) && ($lead->owner_id === $user->id || $lead->owner_id === null))
            || ($user->hasRole(UserRole::Telecaller) && $lead->telecaller_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager)
            || ($user->hasRole(UserRole::Sales) && $lead->owner_id === $user->id)
            || ($user->hasRole(UserRole::Telecaller) && $lead->telecaller_id === $user->id);
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

    /**
     * Whether this user can move a WHOLE other user's book of open leads in
     * one action (the "reassign all of Kiran's leads to Mohit" bulk tool on
     * the Lead Generation list). Deliberately Admin/Manager only — a Sales
     * user reassigning their own individual leads via reassign() above is a
     * different, narrower ability than moving someone else's entire book.
     */
    public function bulkReassign(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
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

    /**
     * Bulk CSV export of the lead list — deliberately Admin-only, not even
     * Manager, same reasoning as CustomerPolicy::export(): a raw data-
     * extraction ability, not a day-to-day CRM action, so it doesn't follow
     * this file's usual "Admin+Manager together" pattern.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }
}
