<?php
$connection = ($user ?? auth()->user())->googleAccountConnection;
?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Google Account</h2>
        <p class="mt-1 text-sm text-gray-600">
            Connect your Google account to import your own Google Meet recordings and transcripts
            into a client's timeline. Read-only — nothing is ever changed in your Calendar or Drive.
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
