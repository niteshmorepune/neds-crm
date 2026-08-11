<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ClientAdvance;
use App\Models\User;

/**
 * Client advances are accounts-team territory only, same access shape as
 * InvoicePolicy::recordPayment() — no Sales access, since this is a money
 * ledger action, not a relationship one.
 */
class ClientAdvancePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->accountsTeam($user);
    }

    public function view(User $user, ClientAdvance $advance): bool
    {
        return $this->accountsTeam($user);
    }

    public function create(User $user): bool
    {
        return $this->accountsTeam($user);
    }

    public function apply(User $user, ClientAdvance $advance): bool
    {
        return $this->accountsTeam($user);
    }

    public function cancel(User $user, ClientAdvance $advance): bool
    {
        return $this->accountsTeam($user);
    }

    private function accountsTeam(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts);
    }
}
