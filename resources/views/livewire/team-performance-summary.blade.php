<div>
    @if ($aiEnabled)
        <div class="mb-3 flex items-center justify-between">
            <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">✨ Generate AI Summary</span>
                <span wire:loading wire:target="generate">Generating…</span>
            </button>
        </div>

        @if (! is_null($summary))
            <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-indigo-700">AI team summary</h3>
                    <button type="button" wire:click="dismiss" class="text-xs text-indigo-500 hover:text-indigo-700">Dismiss</button>
                </div>
                <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $summary }}</div>
                <p class="mt-2 text-xs text-indigo-400">Visible to Admin/Manager only.</p>
            </div>
        @endif
    @endif
</div>
