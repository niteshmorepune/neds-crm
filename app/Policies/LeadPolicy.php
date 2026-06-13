<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

/**
 * All authenticated users with lead-generation menu access can view any lead.
 * Create/update is limited to admin, manager, and sales. Delete to admin/manager.
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
        return $user->hasRole(UserRole::Admin, UserRole::Manager)
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
}
