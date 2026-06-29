<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
