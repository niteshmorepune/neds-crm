<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list filtered in the controller
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $project->owner_id === $user->id
            || $project->assignees()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager) || $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
