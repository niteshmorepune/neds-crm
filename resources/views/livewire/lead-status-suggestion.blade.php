<div class="mt-3 rounded-md border border-purple-200 bg-purple-50 p-3">
    <p class="text-xs font-medium text-purple-700">
        ✏️ Status may need updating — this lead already has notes/calls but is still marked New.
    </p>

    @if (! $requested)
        <button type="button" wire:click="suggest" wire:loading.attr="disabled" wire:target="suggest"
                class="mt-2 inline-flex items-center gap-1 rounded-md border border-purple-300 bg-white px-2.5 py-1 text-xs font-medium text-purple-700 hover:bg-purple-100 disabled:opacity-50">
            <span wire:loading.remove wire:target="suggest">✨ Suggest a status</span>
            <span wire:loading wire:target="suggest">Reading notes…</span>
        </button>
    @else
        @if ($rationale)
            <p class="mt-2 text-xs text-purple-700">✨ {{ $rationale }}</p>
        @elseif ($suggestedStatus === null)
            <p class="mt-2 text-xs text-gray-500">No suggestion available — pick the one that fits below.</p>
        @endif

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <select wire:model="selectedStatus" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-purple-500 focus:ring-purple-500">
                <option value="">— pick a status —</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>
                        {{ $status->label() }}{{ $suggestedStatus === $status->value ? ' (suggested)' : '' }}
                    </option>
                @endforeach
            </select>
            <button type="button" wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                    class="rounded-md bg-purple-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-purple-500 disabled:opacity-50">
                Apply
            </button>
        </div>
        @error('selectedStatus') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @endif
</div>
