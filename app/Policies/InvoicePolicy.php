<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

/**
 * Invoices & payments are the accounts team's domain: admin/manager/accounts
 * have full access. Sales/support have none (also blocked by the invoices menu).
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->accountsTeam($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->accountsTeam($user);
    }

    public function create(User $user): bool
    {
        return $this->accountsTeam($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->accountsTeam($user);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->accountsTeam($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    private function accountsTeam(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }
}
