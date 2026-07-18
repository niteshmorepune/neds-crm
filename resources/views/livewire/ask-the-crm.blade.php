<div>
    @if ($aiEnabled)
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Ask the CRM</h2>
            <p class="mt-1 text-sm text-gray-500">Ask a business question in plain English — answers are grounded only in real figures from the reports below, never invented.</p>

            <form wire:submit="ask" class="mt-4 flex flex-col sm:flex-row gap-2">
                <input type="text" wire:model="question" maxlength="300"
                       placeholder="e.g. Which clients are at risk this month?"
                       class="flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button type="submit" wire:loading.attr="disabled" wire:target="ask"
                        class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                    <span wire:loading.remove wire:target="ask">Ask</span>
                    <span wire:loading wire:target="ask">Thinking…</span>
                </button>
            </form>
            @error('question')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror

            @if ($unsupported)
                <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    <p class="font-medium">I can't answer that one yet. Here's what I can currently answer:</p>
                    <ul class="mt-2 space-y-1 text-xs">
                        @foreach ($this->exampleTopics() as $topic)
                            <li><span class="font-medium">{{ $topic['label'] }}</span> — {{ $topic['description'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($answer)
                <div class="mt-4 rounded-lg bg-indigo-50 border border-indigo-100 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm text-indigo-900">{{ $answer }}</p>
                        <button type="button" wire:click="dismiss" class="shrink-0 text-indigo-400 hover:text-indigo-600" aria-label="Dismiss">&times;</button>
                    </div>

                    @if (! empty($figures))
                        <div class="mt-3 overflow-hidden rounded-md border border-indigo-100 bg-white">
                            <table class="min-w-full text-xs">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($figures as $row)
                                        <tr>
                                            <td class="px-3 py-1.5 text-gray-500">{{ $row['label'] }}</td>
                                            <td class="px-3 py-1.5 text-right font-medium text-gray-900">{{ $row['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($reportRouteName)
                        <a href="{{ route($reportRouteName) }}" class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline">Open {{ $reportLabel }} →</a>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
