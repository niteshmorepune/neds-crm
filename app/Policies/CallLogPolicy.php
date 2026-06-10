<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\User;

class CallLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list filtered: own for staff, all for managers
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, CallLog $callLog): bool
    {
        return $callLog->user_id === $user->id
            || $user->hasRole(UserRole::Admin, UserRole::Manager);
    }
}
