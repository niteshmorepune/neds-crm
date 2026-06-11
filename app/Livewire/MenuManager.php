<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\MenuResolver;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin screen for the data-driven sidebar.
 *
 *  - Role grid: toggles menu_item_role defaults. This IS the source of truth
 *    for route access (with EnsureMenuAccess), so changing it grants/revokes
 *    the underlying permission for that role.
 *  - Per-user overrides: granted / revoked / default on menu_item_user. COSMETIC
 *    ONLY — they show/hide sidebar items but never change route access.
 *
 * Every change flushes the menu cache so it applies on the user's next load.
 */
#[Layout('layouts.app')]
class MenuManager extends Component
{
    public ?int $selectedUserId = null;

    /** Roles that can be toggled. Admin always has all access, so it's omitted. */
    public function roles(): array
    {
        return array_values(array_filter(UserRole::cases(), fn (UserRole $r) => $r !== UserRole::Admin));
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function toggleRole(int $menuItemId, string $role): void
    {
        $this->guard();

        $item = MenuItem::findOrFail($menuItemId);
        $existing = $item->roleAssignments()->where('role', $role);

        if ($existing->exists()) {
            $existing->delete();
        } else {
            $item->roleAssignments()->create(['role' => $role]);
        }

        $this->flush();
    }

    public function setOverride(int $menuItemId, string $access): void
    {
        $this->guard();
        abort_unless($this->selectedUserId !== null, 400);

        $user = User::findOrFail($this->selectedUserId);

        if ($access === 'default') {
            $user->menuOverrides()->detach($menuItemId);
        } else {
            // granted | revoked
            $user->menuOverrides()->syncWithoutDetaching([$menuItemId => ['access' => $access]]);
        }

        $this->flush();
    }

    public function render()
    {
        $items = MenuItem::query()->with('roleAssignments')->orderBy('sort_order')->get();

        // role matrix: [menu_item_id => list<role value the item grants>]
        $matrix = $items->mapWithKeys(fn (MenuItem $item) => [
            $item->id => $item->roleAssignments->pluck('role')
                ->map(fn ($r) => $r instanceof UserRole ? $r->value : $r)->all(),
        ]);

        $overrides = $this->selectedUserId
            ? (User::find($this->selectedUserId)?->menuOverrides()->pluck('access', 'menu_items.id') ?? collect())
            : collect();

        return view('livewire.menu-manager', [
            'items' => $items,
            'roles' => $this->roles(),
            'matrix' => $matrix,
            'users' => User::orderBy('name')->get(['id', 'name', 'role']),
            'overrides' => $overrides,
        ]);
    }

    private function guard(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    private function flush(): void
    {
        app(MenuResolver::class)->flush();
    }
}
