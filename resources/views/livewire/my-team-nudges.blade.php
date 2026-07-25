<div>
    @foreach ($rows as $row)
        @php [$nudge, $status] = [$row['nudge'], $row['status']]; @endphp
        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-amber-50 text-sm">🔔</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">{{ $nudge->title }}</p>
                @if ($nudge->description)
                    <p class="text-xs text-gray-500">{{ $nudge->description }}</p>
                @endif
                @if ($nudge->due_date)
                    <p class="text-xs text-amber-600">Due {{ $nudge->due_date->format('d M Y') }}</p>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <button type="button" wire:click="snooze({{ $status->id }})" class="text-xs text-gray-500 hover:text-gray-700">Snooze 3d</button>
                <button type="button" wire:click="markDone({{ $status->id }})" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-500">Done</button>
            </div>
        </div>
    @endforeach
</div>
