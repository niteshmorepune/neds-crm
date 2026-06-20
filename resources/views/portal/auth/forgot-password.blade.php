<x-portal-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">Forgot your password?</h1>
    <p class="mt-1 text-sm text-gray-500">Enter your email and we'll send you a reset link.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.password.forgot.send') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus />
        </div>
        <x-primary-button class="w-full justify-center">Send reset link</x-primary-button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-500">
        <a href="{{ route('portal.login') }}" class="text-indigo-600 hover:text-indigo-500">Back to sign in</a>
    </p>
</x-portal-guest-layout>
