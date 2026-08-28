<div class="md:col-span-2">
    @if ($this->canGenerate())
        <div class="mb-3 flex items-center justify-between">
            <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">✨ Get call brief</span>
                <span wire:loading wire:target="generate">Preparing brief…</span>
            </button>
        </div>

        @if ($error)
            <p class="mb-3 text-xs text-red-600">{{ $error }}</p>
        @endif

        @if (! is_null($brief))
            <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Pre-call brief</h3>
                    <button type="button" wire:click="dismiss" class="text-xs text-indigo-500 hover:text-indigo-700">Dismiss</button>
                </div>
                <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $brief }}</div>
                <x-ai-feedback method="rate" :value="$briefFeedback" />
            </div>
        @endif
    @endif
</div>
