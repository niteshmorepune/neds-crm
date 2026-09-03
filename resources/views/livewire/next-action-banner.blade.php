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
                <button type="button" wire:click="snooze" class="text-xs text-gray-400 hover:text-gray-600">
                    Snooze 30 min
                </button>
            </div>
        </div>
    @endif
</div>
