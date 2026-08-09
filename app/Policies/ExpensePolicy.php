<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;

/**
 * Straightforward CRUD, no approval workflow — Admin/Manager/Accounts log
 * and manage expenses directly, confirmed with the owner (matches how
 * Subscriptions/Partners work today, not the Leave Requests approval shape).
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }
}
