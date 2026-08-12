<?php

namespace App\Models;

use App\Enums\MenuGroup;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'key',
        'label',
        'group',
        'icon',
        'route',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'group' => MenuGroup::class,
        ];
    }

    /**
     * Route-name patterns that should highlight this sidebar item (for
     * request()->routeIs(...)) — beyond an exact match, most items also
     * match any route under the same first-segment namespace (e.g.
     * 'leads.*' for 'leads.index') so a detail/edit page still highlights
     * its parent. Excluded: a first segment SHARED by multiple unrelated
     * pages (currently only 'reports', which backs ~12 distinct report
     * pages but only 2 sidebar items) would wildcard-match each other's
     * pages too, so those get exact-match only.
     *
     * @return list<string>
     */
    public function activePatterns(): array
    {
        if (! str_contains($this->route, '.')) {
            return [$this->route];
        }

        $prefix = Str::before($this->route, '.');

        if (in_array($prefix, ['reports'], true)) {
            return [$this->route];
        }

        return [$this->route, "{$prefix}.*"];
    }

    /**
     * Role defaults for this item. This pivot is the source of truth for
     * route access (admin bypasses it). See MenuResolver / EnsureMenuAccess.
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(MenuItemRole::class);
    }

    /**
     * Per-user visibility overrides (granted / revoked). COSMETIC ONLY —
     * these never affect route access, which stays role-based.
     */
    public function userOverrides(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('access')
            ->withTimestamps();
    }

    /**
     * Sync the roles allowed to access this item (replaces existing defaults).
     *
     * @param  array<int, UserRole|string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $this->roleAssignments()->delete();

        foreach ($roles as $role) {
            $this->roleAssignments()->create([
                'role' => $role instanceof UserRole ? $role->value : $role,
            ]);
        }
    }
}
