<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Services\MenuResolver;

/**
 * Invoices & payments are primarily the accounts team's domain: admin/manager
 * /accounts have full access including financial mutations. Other roles only
 * gain (read-only) viewing access if an admin explicitly grants the "invoices"
 * menu item to their role via the Menu Controller — this keeps the policy in
 * sync with menu_item_role instead of being a second, independent gate.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->accountsTeam($user) || $this->grantedViaMenu($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($this->accountsTeam($user)) {
            return true;
        }

        if (! $this->grantedViaMenu($user)) {
            return false;
        }

        if ($user->hasRole(UserRole::Sales)) {
            $ownerId = $invoice->customer?->owner_id;

            return $ownerId === $user->id || $ownerId === null;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $this->accountsTeam($user) || $user->hasRole(UserRole::Sales);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isEditable() && $this->accountsTeam($user);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->accountsTeam($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager) && $invoice->payments()->doesntExist();
    }

    private function accountsTeam(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }

    private function grantedViaMenu(User $user): bool
    {
        return app(MenuResolver::class)->canAccess($user, 'invoices');
    }
}
