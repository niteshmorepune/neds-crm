<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // own monthly view; corrections screen gated separately
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $attendance->user_id === $user->id || $this->manages($user);
    }

    /**
     * Only admin/manager may correct attendance (logged to activities).
     */
    public function correct(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
