<?php

namespace App\Http\Controllers;

use App\Actions\ReassignLead;
use App\Enums\LeadReassignmentReason;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Mail\UserInvitation;
use App\Models\User;
use App\Services\MenuResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
            'users' => User::with('additionalRoles')->orderBy('name')->paginate(20),
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
        $additionalRoles = $data['additional_roles'] ?? [];
        unset($data['additional_roles']);

        // No password is collected on this form — the account starts with a
        // random, unusable one and the user sets their own via the invite
        // email below (same Password-broker token + reset flow Breeze's
        // "forgot password" already uses, just with invite-specific wording).
        $data['password'] = Hash::make(Str::random(40));

        $user = User::create($data);
        $this->syncAdditionalRoles($user, $additionalRoles);

        Mail::to($user->email)->send(new UserInvitation($user, Password::createToken($user)));

        return redirect()->route('users.index')->with('status', 'User created — an invitation email has been sent.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'openLeadsCount' => $user->leads()->whereIn('status', LeadStatus::openValues())->count(),
            'reassignCandidates' => User::where('is_active', true)
                ->where('role', UserRole::Sales->value)
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user, MenuResolver $menu, ReassignLead $reassignLead): RedirectResponse
    {
        $data = $request->validated();
        $additionalRoles = $data['additional_roles'] ?? [];
        unset($data['additional_roles']);

        $reassignLeadsToId = $data['reassign_leads_to'] ?? null;
        $reassignReason = LeadReassignmentReason::tryFrom($data['reassign_reason'] ?? '') ?? LeadReassignmentReason::LeftCompany;
        unset($data['reassign_leads_to'], $data['reassign_reason']);

        $roleChanging = isset($data['role']) && $data['role'] !== $user->role->value;
        $wasActive = $user->is_active;

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

        $existingAdditionalRoles = $user->additionalRoles->map(fn ($assignment) => $assignment->role->value)->sort()->values()->all();

        // Captured before update() — once owner_id changes below, $user->leads() would shrink mid-loop.
        $becomingInactive = $wasActive && ! $data['is_active'];
        $openLeads = $becomingInactive && $reassignLeadsToId
            ? $user->leads()->whereIn('status', LeadStatus::openValues())->get()
            : collect();

        $user->update($data);
        $this->syncAdditionalRoles($user, $additionalRoles);

        if ($openLeads->isNotEmpty()) {
            $to = User::findOrFail($reassignLeadsToId);
            $openLeads->each(fn ($lead) => $reassignLead->handle($lead, $to, $request->user(), $reassignReason));
        }

        // Additional roles now expand real menu access (MenuResolver::accessibleKeys()
        // checks the role_user pivot), so a change there needs the same cache
        // flush as a primary role change.
        $additionalRolesChanging = $existingAdditionalRoles !== collect($additionalRoles)->sort()->values()->all();

        if ($roleChanging || $additionalRolesChanging) {
            $menu->flush();
        }

        $status = $openLeads->isNotEmpty()
            ? "User updated. {$openLeads->count()} open lead(s) handed over."
            : 'User updated.';

        return redirect()->route('users.index')->with('status', $status);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        try {
            $user->delete();
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return redirect()->route('users.index')->with(
                'error',
                "{$user->name} can't be deleted — they created content pieces (SMDost briefs) that reference them. Reassign or delete those first, or deactivate the user instead."
            );
        }

        return redirect()->route('users.index')->with('status', 'User deleted.');
    }

    /**
     * Replace a user's additional (non-primary) roles. A value duplicating
     * the user's current primary role is dropped silently — redundant, not
     * invalid. additionalRoles() is a HasMany (roles have no lookup table of
     * their own), so there's no sync() available: delete-then-recreate is
     * simplest at this low per-user row count.
     *
     * @param  list<string>  $roles
     */
    private function syncAdditionalRoles(User $user, array $roles): void
    {
        $roles = array_values(array_diff($roles, [$user->role->value]));

        $user->additionalRoles()->delete();
        foreach ($roles as $role) {
            $user->additionalRoles()->create(['role' => $role]);
        }
    }
}
