<div>
    @if ($aiEnabled)
        @if (is_null($suggestion))
            <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">✨ Suggest action</span>
                <span wire:loading wire:target="generate">Thinking…</span>
            </button>
        @else
            <div class="mt-2 rounded-md border border-indigo-200 bg-indigo-50 p-3">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-700">AI suggestion</h4>
                    <button type="button" wire:click="dismiss" class="text-xs text-indigo-500 hover:text-indigo-700">Dismiss</button>
                </div>
                <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $suggestion }}</p>
            </div>
        @endif
    @endif
</div>
