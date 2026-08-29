<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

/**
 * Client (Customer) access rules:
 *  - admin/manager/support/accounts: can view and list all clients.
 *  - support: view-only — cannot edit client profiles or manage contacts.
 *  - sales: can only view/edit/manage clients they own or that are unassigned
 *    — UNLESS they also hold one of the full-access roles above (multi-role:
 *    an additional role must only ever WIDEN access, never narrow below what
 *    any one of the user's roles would independently grant — see the
 *    2026-08-16 multi-role decisions log and its 2026-08-29 follow-up in
 *    CLAUDE.md). So Sales-only narrowing applies solely when Sales is the
 *    user's one and only qualifying role here.
 *  - telecaller: view-only, same as support — clients aren't part of their
 *    scope (leads + calling only); listed explicitly in update()'s deny-list
 *    since this method otherwise defaults to true for any non-Sales role.
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
        if ($this->hasFullAccessRole($user)) {
            return true;
        }

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
            UserRole::Accounts,
        );
    }

    public function update(User $user, Customer $customer): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        // Sales is checked before the Intern/Support/Telecaller deny-list
        // below so a Sales rep who also picks up Support (or Intern/
        // Telecaller) as an additional role keeps editing their own clients
        // — the deny-list must never veto a grant Sales alone would give.
        // (Accounts isn't in that deny-list at all, so it still falls
        // through to the final `true` untouched, same as before.)
        if ($user->hasRole(UserRole::Sales)) {
            return $this->salesOwnsOrUnassigned($user, $customer);
        }

        if ($user->hasRole(UserRole::Intern, UserRole::Support, UserRole::Telecaller)) {
            return false;
        }

        return true;
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
     * Manage nested resources (contacts): Admin/Manager full access; Sales
     * only for their own/unassigned clients. Support and Accounts can view
     * contacts but not add/edit/delete them.
     */
    public function manage(User $user, Customer $customer): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        return $user->hasRole(UserRole::Sales) && $this->salesOwnsOrUnassigned($user, $customer);
    }

    /**
     * Create/import Google Meet notes on this client — deliberately its own
     * check, not manage() (contacts). Meet notes are conceptually adjacent to
     * Call Logs (both are "communication records with a client"), so this
     * mirrors Calling's menu-access role set exactly: Admin/Manager/Sales/
     * Support, no ownership restriction — the whole point of the shared
     * company Google connection is that any of these roles can schedule a
     * meeting or log notes for any client, not just their own.
     */
    public function manageMeetings(User $user, Customer $customer): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales, UserRole::Support);
    }

    /**
     * Create/edit/delete Important Links (website, Drive, Meet, social,
     * etc.) on this client — deliberately its own check, not manage()
     * (contacts/notes), same reasoning as manageMeetings(): a link is a
     * shared client resource any of these roles may need to add, not
     * sales-owned relationship data, so no ownership restriction.
     */
    public function manageLinks(User $user, Customer $customer): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales, UserRole::Support);
    }

    /**
     * Create/edit/delete service assignments, service-specific links, Client
     * Requirements, and Client Assets & Documents on this client — same
     * "shared client resource, not sales-owned relationship data" reasoning
     * as manageMeetings()/manageLinks(), and deliberately one gate covering
     * all four rather than four near-identical policy methods.
     */
    public function manageServices(User $user, Customer $customer): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Sales, UserRole::Support);
    }

    /**
     * Any role that independently grants full (unowned) client visibility —
     * checked before the Sales narrowing in view(), so a Sales rep who also
     * holds one of these as an additional role isn't wrongly narrowed down
     * to their own/unassigned clients (see view()'s docblock note above).
     */
    private function hasFullAccessRole(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Support, UserRole::Accounts);
    }

    private function salesOwnsOrUnassigned(User $user, Customer $customer): bool
    {
        return $customer->owner_id === $user->id || $customer->owner_id === null;
    }
}
