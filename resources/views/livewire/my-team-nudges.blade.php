<div class="rounded-lg bg-white p-5 shadow-sm">
    <h3 class="text-base font-semibold text-gray-900">
        Reminders
        @if ($rows->isNotEmpty())
            <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $rows->count() }}</span>
        @endif
    </h3>

    @if ($rows->isEmpty())
        <p class="mt-2 text-sm text-gray-400">Nothing pending. 🎉</p>
    @else
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @foreach ($rows as $row)
                @php [$nudge, $status] = [$row['nudge'], $row['status']]; @endphp
                <li class="py-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $nudge->title }}</p>
                            @if ($nudge->description)
                                <p class="mt-0.5 text-xs text-gray-500">{{ $nudge->description }}</p>
                            @endif
                            @if ($nudge->due_date)
                                <p class="mt-0.5 text-xs text-amber-600">Due {{ $nudge->due_date->format('d M Y') }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <button type="button" wire:click="snooze({{ $status->id }})" class="text-xs text-gray-500 hover:text-gray-700">Snooze 3d</button>
                            <button type="button" wire:click="markDone({{ $status->id }})" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-500">Done</button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
