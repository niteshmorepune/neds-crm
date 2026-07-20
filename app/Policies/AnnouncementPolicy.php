<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
