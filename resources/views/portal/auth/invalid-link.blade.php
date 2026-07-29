<x-portal-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">This link is no longer valid</h1>
    <p class="mt-1 text-sm text-gray-500">
        It may have already been used, a newer link was sent since, or your
        account's access has changed.
    </p>

    <div class="mt-6 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
        Try requesting a new link below. If that doesn't work either, contact
        your account manager and ask them to resend your portal invitation.
    </div>

    <a href="{{ route('portal.password.forgot') }}"
       class="mt-6 inline-flex w-full items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        Request a new link
    </a>

    <p class="mt-4 text-center text-sm text-gray-500">
        <a href="{{ route('portal.login') }}" class="text-indigo-600 hover:text-indigo-500">Back to sign in</a>
    </p>
</x-portal-guest-layout>
