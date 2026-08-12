<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Real role-based visibility for a record, shared by TeamResource and
 * ImportantLink (each backed by its own small "{model}_role" pivot table —
 * same shape as MenuItem/MenuItemRole, just two copies since only these two
 * models need it).
 *
 * No visibleRoles rows = visible to everyone (the non-restrictive default).
 * Admin and Manager always bypass, matching this app's "Admin/Manager see
 * everything" convention. Role matching uses allRoles() (primary +
 * additional roles) — same union rule as the Menu Controller sidebar, since
 * this is an access question, not a "which single panel" question.
 *
 * Keep isVisibleTo() (used by a Policy's view()) in sync with
 * scopeVisibleTo() (used by list queries) — same discipline CLAUDE.md
 * already asks for between a model's scopeVisibleTo() and its Policy.
 */
trait HasRoleVisibility
{
    public function visibleRoles(): HasMany
    {
        return $this->hasMany($this->roleVisibilityModel());
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return $query;
        }

        $roleValues = $user->allRoles()->map(fn (UserRole $role) => $role->value)->all();

        return $query->where(fn (Builder $q) => $q->doesntHave('visibleRoles')
            ->orWhereHas('visibleRoles', fn (Builder $q2) => $q2->whereIn('role', $roleValues)));
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return true;
        }

        $restrictedTo = $this->visibleRoles->pluck('role')->all(); // array<UserRole> (enum-cast pivot)

        if ($restrictedTo === []) {
            return true;
        }

        return $user->allRoles()->contains(fn (UserRole $role) => in_array($role, $restrictedTo, true));
    }

    /**
     * Replace this record's role restrictions. Empty array = visible to everyone.
     *
     * @param  array<int, UserRole|string>  $roles
     */
    public function syncVisibleRoles(array $roles): void
    {
        $this->visibleRoles()->delete();

        foreach ($roles as $role) {
            $this->visibleRoles()->create([
                'role' => $role instanceof UserRole ? $role->value : $role,
            ]);
        }
    }

    abstract protected function roleVisibilityModel(): string;
}
