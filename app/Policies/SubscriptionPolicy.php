<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Subscription;
use App\Models\User;

/**
 * Deliberately narrower than most admin-ish modules (Festival, Partner,
 * Client Radar are Admin+Manager) — internal vendor/billing info the owner
 * asked to keep Admin-only.
 */
class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
