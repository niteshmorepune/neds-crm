<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the sidebar menu and route access for a user.
 *
 * Two distinct concepts, deliberately kept separate:
 *
 *  - ACCESS (security): which menu keys a user's ROLE(s) may reach — primary
 *    role plus any additional roles (role_user pivot). Admin gets everything.
 *    Used by the EnsureMenuAccess middleware to protect routes. Per-user
 *    overrides (menu_item_user) do NOT affect this.
 *
 *  - VISIBILITY (cosmetic): which items show in the sidebar. Starts from the
 *    role defaults, then applies per-user overrides (granted shows an item,
 *    revoked hides it). A "granted" item the role cannot access will still
 *    show but 403 when clicked — that is intentional: hiding/showing a menu is
 *    never a security control.
 *
 * Results are cached. A global version counter (bumped via flush()) invalidates
 * every cached entry at once, which works on the database/file cache stores
 * used on shared hosting (no cache tags required).
 */
class MenuResolver
{
    private const VERSION_KEY = 'menu:version';

    private const TTL = 86400; // 24h; explicit busting via flush() keeps it fresh

    /**
     * Ordered menu items the user should see in the sidebar.
     *
     * @return Collection<int, MenuItem>
     */
    public function visibleItems(User $user): Collection
    {
        return Cache::remember(
            $this->key("visible:user:{$user->getKey()}"),
            self::TTL,
            fn () => $this->computeVisibleItems($user),
        );
    }

    /**
     * Menu keys the user's role(s) may access — primary role plus any
     * additional roles. Admin sees all. Cached per-user (rather than
     * per-role) since additional roles make the set of granted keys
     * user-specific, not just role-specific.
     *
     * @return array<int, string>
     */
    public function accessibleKeys(User $user): array
    {
        if ($user->isAdmin()) {
            return Cache::remember(
                $this->key('access:admin'),
                self::TTL,
                fn () => MenuItem::query()->pluck('key')->all(),
            );
        }

        return Cache::remember(
            $this->key("access:user:{$user->getKey()}"),
            self::TTL,
            fn () => MenuItem::query()
                ->whereHas('roleAssignments', fn ($q) => $q->whereIn(
                    'role',
                    $user->allRoles()->map(fn ($role) => $role->value)->all(),
                ))
                ->pluck('key')
                ->all(),
        );
    }

    /**
     * Whether the user may access the route behind a given menu key.
     */
    public function canAccess(User $user, string $key): bool
    {
        return in_array($key, $this->accessibleKeys($user), true);
    }

    /**
     * Invalidate all cached menu data (call after editing menu items,
     * role defaults, or per-user overrides). Bumps a version counter so every
     * versioned key is bypassed. We read-then-write with forever() rather than
     * Cache::increment(), because increment() is a no-op on a never-initialised
     * key in the database/file cache stores used on shared hosting.
     */
    public function flush(): void
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        Cache::forever(self::VERSION_KEY, $version + 1);
    }

    /**
     * @return Collection<int, MenuItem>
     */
    private function computeVisibleItems(User $user): Collection
    {
        $all = MenuItem::query()->orderBy('sort_order')->get();

        if ($user->isAdmin()) {
            return $all->values();
        }

        $accessible = collect($this->accessibleKeys($user));

        $overrides = $user->menuOverrides()
            ->pluck('access', 'menu_items.id'); // [menu_item_id => granted|revoked]

        return $all->filter(function (MenuItem $item) use ($accessible, $overrides) {
            $override = $overrides->get($item->getKey());

            if ($override === 'revoked') {
                return false;
            }

            if ($override === 'granted') {
                return true;
            }

            return $accessible->contains($item->key);
        })->values();
    }

    private function key(string $suffix): string
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return "menu:v{$version}:{$suffix}";
    }
}
