@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="name" value="Name *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="email" value="Email *" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required />
        <x-input-error :messages="$errors->get('email')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="role" value="Role *" />
        <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('role')" class="mt-1" />
        @if ($user->exists && $user->id === auth()->id())
            <p class="mt-1 text-xs text-amber-600">You can't change your own role or disable your own account.</p>
        @endif
    </div>

    @php
        $selectedAdditionalRoles = old('additional_roles', $user->exists ? $user->additionalRoles->pluck('role')->map(fn ($r) => $r->value)->all() : []);
    @endphp
    <div>
        <x-input-label value="Additional roles" />
        <p class="mt-1 text-xs text-gray-500">Grants extra permissions/notifications on top of the primary role above. Does not change the sidebar or dashboard, which always follow the primary role.</p>
        <div class="mt-2 flex flex-wrap gap-4">
            @foreach ($roles as $role)
                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" name="additional_roles[]" value="{{ $role->value }}"
                           class="rounded border-gray-300 text-indigo-600"
                           @checked(in_array($role->value, $selectedAdditionalRoles, true))>
                    {{ $role->label() }}
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get('additional_roles')" class="mt-1" />
    </div>

    @if ($user->exists)
        <div>
            <x-input-label for="password" value="New password (leave blank to keep current)" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
        </div>
    @else
        <p class="text-sm text-gray-500">An invitation email with a "Set my password" link will be sent to this address once the account is created.</p>
    @endif

    <div>
        <x-input-label for="device_user_id" value="Biometric Device User ID" />
        <x-text-input id="device_user_id" name="device_user_id" type="text" class="mt-1 block w-full"
                      :value="old('device_user_id', $user->device_user_id)" placeholder="e.g. 3" />
        <p class="mt-1 text-xs text-gray-500">Numeric ID from the biometric machine's Device Users list. Leave blank if this user does not use the biometric device.</p>
        <x-input-error :messages="$errors->get('device_user_id')" class="mt-1" />
    </div>

    @if ($user->exists && $openLeadsCount > 0)
        <div x-data="{ activeChecked: {{ old('is_active', $user->is_active) ? 'true' : 'false' }} }">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" x-model="activeChecked" class="rounded border-gray-300 text-indigo-600"
                       @checked(old('is_active', $user->is_active ?? true))
                       @disabled($user->exists && $user->id === auth()->id())>
                Active (can log in)
            </label>

            <div x-show="!activeChecked" x-cloak class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-medium text-amber-800">{{ $user->name }} currently owns {{ $openLeadsCount }} open lead(s).</p>
                <p class="mt-1 text-xs text-amber-700">Deactivating won't move them automatically — choose who takes over.</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="reassign_leads_to" value="Hand over open leads to" />
                        <select id="reassign_leads_to" name="reassign_leads_to" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">— Leave assigned to {{ $user->name }} —</option>
                            @foreach ($reassignCandidates as $candidate)
                                <option value="{{ $candidate->id }}" @selected((string) old('reassign_leads_to') === (string) $candidate->id)>{{ $candidate->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('reassign_leads_to')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="reassign_reason" value="Reason" />
                        <select id="reassign_reason" name="reassign_reason" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @foreach (\App\Enums\LeadReassignmentReason::cases() as $reasonOption)
                                <option value="{{ $reasonOption->value }}" @selected(old('reassign_reason', 'left_company') === $reasonOption->value)>{{ $reasonOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    @else
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600"
                   @checked(old('is_active', $user->is_active ?? true))
                   @disabled($user->exists && $user->id === auth()->id())>
            Active (can log in)
        </label>
    @endif

    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        <x-primary-button>{{ $user->exists ? 'Save changes' : 'Create user' }}</x-primary-button>
    </div>
</div>
