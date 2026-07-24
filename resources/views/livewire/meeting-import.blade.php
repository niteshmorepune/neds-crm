<div>
    @if ($featureEnabled && $canManage)
        <div class="mb-4">
            @if (! $connected)
                <p class="text-sm text-gray-400">
                    <a href="{{ route('profile.edit') }}" class="text-indigo-600 hover:underline">Connect your Google account</a>
                    to import Meet notes here.
                </p>
            @elseif (! $showPicker)
                <button type="button" wire:click="loadEvents" wire:loading.attr="disabled" wire:target="loadEvents"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    <span wire:loading.remove wire:target="loadEvents">+ Import Meet Notes</span>
                    <span wire:loading wire:target="loadEvents">Loading your Calendar…</span>
                </button>
            @endif

            @if ($error)
                <p class="mt-2 text-xs text-red-600">{{ $error }}</p>
            @endif

            @if ($showPicker)
                <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500">Your recent Meet calls (last 14 days)</p>
                        <button type="button" wire:click="cancelPicker" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($events as $event)
                            <li class="flex items-center justify-between py-2">
                                <div>
                                    <p class="text-sm text-gray-800">{{ $event['title'] }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $event['start']->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                                        @if (! empty($event['attendees']))
                                            · {{ implode(', ', array_slice($event['attendees'], 0, 3)) }}{{ count($event['attendees']) > 3 ? '…' : '' }}
                                        @endif
                                    </p>
                                </div>
                                <button type="button" wire:click="importEvent('{{ $event['id'] }}')" wire:loading.attr="disabled"
                                        class="shrink-0 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                                    Import
                                </button>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-gray-400">No Meet calls found in the last 14 days.</li>
                        @endforelse
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <ul class="space-y-3">
        @forelse ($meetings as $meeting)
            <li class="rounded-md border border-gray-100 p-3">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-800">{{ $meeting->title }}</p>
                    <p class="text-xs text-gray-400">
                        {{ $meeting->occurred_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                        @if ($meeting->duration_minutes) · {{ $meeting->duration_minutes }}m @endif
                        · {{ $meeting->user?->name }}
                    </p>
                </div>
                @if (! empty($meeting->attendees))
                    <p class="mt-1 text-xs text-gray-400">With: {{ implode(', ', $meeting->attendees) }}</p>
                @endif
                <div class="mt-2 flex items-center gap-3 text-xs">
                    @if ($meeting->drive_recording_url)
                        <a href="{{ $meeting->drive_recording_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">▶ Recording</a>
                    @endif
                    @if ($meeting->drive_transcript_url)
                        <a href="{{ $meeting->drive_transcript_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">Transcript (Drive)</a>
                    @endif
                </div>
                @if ($meeting->raw_transcript)
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-medium text-gray-500">View transcript</summary>
                        <p class="mt-1 max-h-48 overflow-y-auto whitespace-pre-wrap text-xs text-gray-600">{{ $meeting->raw_transcript }}</p>
                    </details>
                @endif
            </li>
        @empty
            <li class="text-sm text-gray-400">No Meet notes imported yet.</li>
        @endforelse
    </ul>
</div>
