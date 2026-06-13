<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use App\Services\MenuResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Admin-only staff management. Public registration is disabled, so this is how
 * accounts are created. Guards prevent an admin from locking themselves out
 * (changing their own role away from admin, disabling, or deleting themselves).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('users.index', [
            'users' => User::orderBy('name')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('users.create', [
            'user' => new User(['is_active' => true]),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('users.index')->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user, MenuResolver $menu): RedirectResponse
    {
        $data = $request->validated();

        $roleChanging = isset($data['role']) && $data['role'] !== $user->role->value;

        // An admin can't demote or disable their own account.
        if ($user->id === $request->user()->id) {
            $data['role'] = $user->role->value;
            $data['is_active'] = true;
            $roleChanging = false;
        }

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($roleChanging) {
            $menu->flush();
        }

        return redirect()->route('users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }
}
