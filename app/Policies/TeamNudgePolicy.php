<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TeamNudge;
use App\Models\User;

class TeamNudgePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function view(User $user, TeamNudge $nudge): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function update(User $user, TeamNudge $nudge): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function delete(User $user, TeamNudge $nudge): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
