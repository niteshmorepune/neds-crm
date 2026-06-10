<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

/**
 * Client (Customer) access rules:
 *  - admin / manager / support / accounts: full access to all clients.
 *  - sales: only clients they own or that are unassigned.
 *
 * Decided with the owner (2026-06-10): support & accounts see all because
 * they work across clients (tickets, invoices). Keep scopeVisibleTo() on the
 * Customer model in sync with view().
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        // List is allowed for everyone with menu access; rows are filtered by
        // Customer::scopeVisibleTo so sales only see permitted clients.
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
