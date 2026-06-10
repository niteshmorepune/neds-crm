<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'key',
        'label',
        'icon',
        'route',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
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
