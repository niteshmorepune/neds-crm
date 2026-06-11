<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Enter the 6-digit code from your authenticator app to continue. You can also use one of your recovery codes.
    </div>

    <form method="POST" action="{{ route('two-factor.challenge.store') }}">
        @csrf
        <div>
            <x-input-label for="code" :value="__('Authentication code')" />
            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code"
                          inputmode="numeric" autocomplete="one-time-code" autofocus required />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>{{ __('Verify') }}</x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">Log out</button>
    </form>
</x-guest-layout>
