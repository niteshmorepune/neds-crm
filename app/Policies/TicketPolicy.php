<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

/**
 * Tickets are handled by the support team (support/manager/admin: full access).
 * Sales may see and raise tickets for their own clients. Accounts have none.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Support, UserRole::Sales);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($this->supportTeam($user)) {
            return true;
        }

        return $user->hasRole(UserRole::Sales) && $this->salesOwnsClient($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    private function supportTeam(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Support);
    }

    private function salesOwnsClient(User $user, Ticket $ticket): bool
    {
        $ownerId = $ticket->customer?->owner_id;

        return $ownerId === $user->id || $ownerId === null;
    }
}
