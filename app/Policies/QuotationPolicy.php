<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Quotation;
use App\Models\User;

/**
 * Quotations: admin/manager/accounts see all; sales see quotations for clients
 * visible to them (own + unassigned). Matches the quotations menu roles.
 */
class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts, UserRole::Sales);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts)) {
            return true;
        }

        return $user->hasRole(UserRole::Sales) && $this->salesCanSeeCustomer($user, $quotation);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts, UserRole::Sales);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $quotation->isEditable() && $this->view($user, $quotation);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function convert(User $user, Quotation $quotation): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }

    private function salesCanSeeCustomer(User $user, Quotation $quotation): bool
    {
        $ownerId = $quotation->customer?->owner_id;

        return $ownerId === $user->id || $ownerId === null;
    }
}
