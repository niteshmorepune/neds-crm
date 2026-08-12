<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TeamResource;
use App\Models\User;

/**
 * Resource Library access: any authenticated staff member may view the list
 * (route already gated by menu.access:important-links; per-item visibility
 * is enforced separately by isVisibleTo()/scopeVisibleTo(), see
 * HasRoleVisibility). Upload/edit/delete is Admin/Manager only.
 *
 * Keep isVisibleTo() on the model in sync with view() — same discipline
 * CLAUDE.md asks for between scopeVisibleTo() and a Policy elsewhere.
 */
class TeamResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TeamResource $resource): bool
    {
        return $resource->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function update(User $user, TeamResource $resource): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function delete(User $user, TeamResource $resource): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
