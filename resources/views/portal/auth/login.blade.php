<x-portal-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">Sign in</h1>
    <p class="mt-1 text-sm text-gray-500">Access your invoices, projects and tickets.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus />
        </div>
        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600" /> Remember me
        </label>
        <x-primary-button class="w-full justify-center">Sign in</x-primary-button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-500">
        <a href="{{ route('portal.password.forgot') }}" class="text-indigo-600 hover:text-indigo-500">Forgot your password?</a>
    </p>
</x-portal-guest-layout>
