<div>
    @if ($aiEnabled)
        <button type="button" wire:click="suggest" wire:loading.attr="disabled" wire:target="suggest"
                class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
            <span wire:loading.remove wire:target="suggest">✨ Suggest onboarding tasks</span>
            <span wire:loading wire:target="suggest">Thinking…</span>
        </button>

        @if ($error)
            <p class="mt-1.5 text-xs text-amber-600">{{ $error }}</p>
        @endif

        @if ($hasSuggested && ! $error && empty($suggestions))
            <p class="mt-1.5 text-xs text-gray-400">Nothing specific to suggest — the standard checklist has this covered.</p>
        @endif

        @if (! empty($suggestions))
            <div class="mt-3 rounded-md border border-indigo-200 bg-indigo-50 p-3">
                <p class="text-xs font-medium text-indigo-700">Suggested — untick anything you don't want, then add:</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($suggestions as $i => $suggestion)
                        <li class="flex items-start gap-2 text-sm">
                            <input type="checkbox" wire:model="suggestions.{{ $i }}.selected" class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $suggestion['title'] }}</p>
                                <p class="text-xs text-gray-500">{{ $suggestion['description'] }} &middot; due in {{ $suggestion['due_in_days'] }} days</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3 flex items-center gap-3">
                    <button type="button" wire:click="addSelected" wire:loading.attr="disabled" wire:target="addSelected"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                        Add selected tasks
                    </button>
                    <x-ai-feedback method="rateSuggestion" :value="$suggestionFeedback" />
                </div>
            </div>
        @endif
    @endif
</div>
