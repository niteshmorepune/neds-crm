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

    <div>
        <x-input-label for="password" :value="$user->exists ? 'New password (leave blank to keep current)' : 'Password *'" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $user->exists" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="password_confirmation" value="Confirm password" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $user->exists" autocomplete="new-password" />
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600"
               @checked(old('is_active', $user->is_active ?? true))
               @disabled($user->exists && $user->id === auth()->id())>
        Active (can log in)
    </label>

    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        <x-primary-button>{{ $user->exists ? 'Save changes' : 'Create user' }}</x-primary-button>
    </div>
</div>
