<div>
    @if ($aiEnabled)
        <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click="$wire.suggest(
                        document.getElementById('customer_id').value ? parseInt(document.getElementById('customer_id').value) : null,
                        document.getElementById('subject').value,
                        document.getElementById('description').value
                    )"
                    wire:loading.attr="disabled" wire:target="suggest"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                <span wire:loading.remove wire:target="suggest">✨ Suggest priority &amp; assignee</span>
                <span wire:loading wire:target="suggest">Thinking…</span>
            </button>
            <span class="text-xs text-gray-400">Fills in the fields above — review before submitting.</span>
        </div>

        @if ($error)
            <p class="mt-1.5 text-xs text-amber-600">{{ $error }}</p>
        @endif

        @if ($reason)
            <div class="mt-2 rounded-md border border-indigo-200 bg-indigo-50 p-3 text-sm text-gray-700">
                <p><span class="font-medium text-indigo-700">{{ $priorityLabel }} priority</span>@if ($serviceName) &middot; {{ $serviceName }}@endif@if ($assigneeName) &middot; suggested {{ $assigneeName }}@endif</p>
                <p class="mt-1 text-xs text-gray-500">{{ $reason }}</p>
                <x-ai-feedback method="rateSuggestion" :value="$suggestionFeedback" />
            </div>
        @endif
    @endif
</div>
