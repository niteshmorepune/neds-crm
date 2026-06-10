<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class DailyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // everyone submits + sees their own
    }

    /**
     * Only admin/manager may see the whole team's daily reports.
     */
    public function viewTeam(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
