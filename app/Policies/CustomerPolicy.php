<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

/**
 * Client (Customer) access rules:
 *  - admin/manager/support/accounts: can view and list all clients.
 *  - sales: can only view/edit/manage clients they own or that are unassigned.
 *  - admin / manager: full access including delete.
 *
 * Keep scopeVisibleTo() on the Customer model in sync with view().
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $user->hasRole(UserRole::Sales)) {
            return true;
        }

        return $this->salesOwnsOrUnassigned($user, $customer);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(
            UserRole::Admin,
            UserRole::Manager,
            UserRole::Sales,
            UserRole::Support,
            UserRole::Accounts,
        );
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $user->hasRole(UserRole::Sales)) {
            return true;
        }

        return $this->salesOwnsOrUnassigned($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        // Only admin/manager may remove a client; or the owning sales rep.
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $user->hasRole(UserRole::Sales) && $customer->owner_id === $user->id;
    }

    /**
     * Manage nested resources (contacts, notes): allowed if the user can
     * update the parent client.
     */
    public function manage(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }

    private function salesOwnsOrUnassigned(User $user, Customer $customer): bool
    {
        return $customer->owner_id === $user->id || $customer->owner_id === null;
    }
}
