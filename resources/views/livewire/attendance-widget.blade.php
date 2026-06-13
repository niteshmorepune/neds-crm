<div class="rounded-lg bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Attendance — {{ now()->timezone(config('app.display_timezone'))->format('d M Y') }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                @if ($attendance && $attendance->check_in_at)
                    Checked in at {{ $attendance->check_in_at->timezone(config('app.display_timezone'))->format('g:i A') }}
                    @if ($attendance->check_out_at) · out at {{ $attendance->check_out_at->timezone(config('app.display_timezone'))->format('g:i A') }} @endif
                @else
                    Not checked in yet.
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if (! $attendance || ! $attendance->check_in_at)
                <button wire:click="checkIn" class="rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-500">Check in</button>
            @elseif (! $attendance->check_out_at)
                <button wire:click="checkOut" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Check out</button>
            @else
                <span class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-600">Done for today</span>
            @endif
            <a href="{{ route('attendance.index') }}" class="text-sm text-indigo-600 hover:underline">My attendance</a>
        </div>
    </div>
</div>
