<div wire:poll.45s="poll">
    @if ($action)
        <div class="fixed bottom-4 right-4 z-50 w-full max-w-sm rounded-lg border border-indigo-200 bg-white p-4 shadow-lg">
            <p class="text-sm font-semibold text-gray-900">✨ {{ $action['title'] }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $action['body'] }}</p>
            <div class="mt-3 flex items-center gap-3">
                @if ($action['action_url'])
                    <a href="{{ $action['action_url'] }}"
                       @if ($action['external']) target="_blank" rel="noopener" @endif
                       class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                        {{ $action['action_label'] }}
                    </a>
                @else
                    <button type="button" wire:click="complete"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                        {{ $action['action_label'] }}
                    </button>
                @endif
                <x-dropdown align="left" width="44">
                    <x-slot name="trigger">
                        <button type="button" class="flex items-center text-xs text-gray-400 hover:text-gray-600">
                            Snooze
                            <svg class="ms-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        @foreach (\App\Livewire\NextActionBanner::SNOOZE_TIERS as $tier => $label)
                            <button type="button" wire:click="snooze('{{ $tier }}')"
                                    class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100">
                                {{ $label }}
                            </button>
                        @endforeach
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    @endif
</div>
