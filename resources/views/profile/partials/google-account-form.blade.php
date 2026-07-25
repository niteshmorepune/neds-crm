<?php
// Company-wide, not this specific viewer's own row — a different admin may
// have been the one who originally connected it (App\Models\GoogleAccountConnection::forCompany()
// resolves to whichever Admin's connection is most recently connected).
$connection = \App\Models\GoogleAccountConnection::forCompany();
?>

@if (($user ?? auth()->user())->isAdmin())
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Google Account</h2>
        <p class="mt-1 text-sm text-gray-600">
            Connect NEDS's Google account here — this is a single company-wide connection, used to
            create Meet links for any staff member's client/lead meetings and to read the resulting
            recordings and transcripts back onto the right client or lead. Only Admin connects this;
            everyone else just uses "Create Meeting" on a client or lead page once it's set up.
        </p>
    </header>

    @if (session('status') === 'google-connected')
        <div class="mt-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">Google account connected.</div>
    @elseif (session('status') === 'google-connect-failed')
        <div class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">Couldn't connect your Google account — please try again.</div>
    @elseif (session('status') === 'google-disconnected')
        <div class="mt-4 rounded-md bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700">Google account disconnected.</div>
    @endif

    <div class="mt-6">
        @if ($connection)
            <p class="inline-flex items-center gap-2 text-sm font-medium text-green-700">
                <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                Connected{{ $connection->google_email ? " as {$connection->google_email}" : '' }}.
            </p>
            <form method="POST" action="{{ route('google.disconnect') }}" class="mt-4">
                @csrf @method('DELETE')
                <x-danger-button>Disconnect</x-danger-button>
            </form>
        @else
            <a href="{{ route('google.redirect') }}">
                <x-primary-button type="button">Connect Google Account</x-primary-button>
            </a>
        @endif
    </div>
</section>
@endif
