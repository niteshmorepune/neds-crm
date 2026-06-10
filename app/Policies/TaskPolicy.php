<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list filtered in the controller (My Tasks etc.)
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $this->isParticipant($user, $task);
    }

    public function create(User $user): bool
    {
        return true; // any staff member can create a task
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $this->isParticipant($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager) || $task->created_by === $user->id;
    }

    private function isParticipant(User $user, Task $task): bool
    {
        return $task->assignee_id === $user->id
            || $task->created_by === $user->id
            || ($task->project_id !== null && $task->project?->assignees()->whereKey($user->id)->exists());
    }
}
