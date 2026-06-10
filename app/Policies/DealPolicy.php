<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\User;

/**
 * Deals mirror leads: admin & manager see all; sales see own + unassigned;
 * support & accounts have no access. Keep in sync with Deal::scopeVisibleTo.
 */
class DealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    public function view(User $user, Deal $deal): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $user->hasRole(UserRole::Sales) && $this->ownsOrUnassigned($user, $deal);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    public function update(User $user, Deal $deal): bool
    {
        return $this->view($user, $deal);
    }

    public function delete(User $user, Deal $deal): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $user->hasRole(UserRole::Sales) && $deal->owner_id === $user->id;
    }

    private function ownsOrUnassigned(User $user, Deal $deal): bool
    {
        return $deal->owner_id === $user->id || $deal->owner_id === null;
    }
}
