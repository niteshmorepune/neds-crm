<x-portal-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">Set your password</h1>
    <p class="mt-1 text-sm text-gray-500">Choose a password to activate your portal access.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.password.store', $token) }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <x-input-label for="password" value="New password" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autofocus />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
        </div>
        <x-primary-button class="w-full justify-center">Set password & sign in</x-primary-button>
    </form>
</x-portal-guest-layout>
