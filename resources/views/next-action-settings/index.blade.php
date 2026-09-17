<x-app-layout>
    <x-slot name="header">Notification Settings</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Next Action pop-up</h2>
            <p class="mt-1 text-xs text-gray-400">
                The "next thing to do" pop-up that appears across the app for every user. Pausing it here
                turns it off for <strong>everyone, every role</strong>, until an Admin or Manager turns it
                back on &mdash; it's a company-wide switch, separate from the per-prompt Snooze button on
                the pop-up itself.
            </p>

            <div class="mt-4 flex items-center gap-3">
                @if ($setting->paused)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">Paused</span>
                    <span class="text-xs text-gray-500">
                        @if ($setting->updatedBy)
                            Paused by {{ $setting->updatedBy->name }}, {{ $setting->updated_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') }}
                        @else
                            Paused {{ $setting->updated_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') }}
                        @endif
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">Active</span>
                    <span class="text-xs text-gray-500">
                        @if ($setting->updatedBy)
                            Resumed by {{ $setting->updatedBy->name }}, {{ $setting->updated_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y, h:i A') }}
                        @endif
                    </span>
                @endif
            </div>

            <div class="mt-4">
                @if ($setting->paused)
                    <form method="POST" action="{{ route('next-action-settings.resume') }}">
                        @csrf
                        <x-primary-button>Resume for everyone</x-primary-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('next-action-settings.pause') }}">
                        @csrf
                        <x-danger-button>Pause for everyone</x-danger-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
