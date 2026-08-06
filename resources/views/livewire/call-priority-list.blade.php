<div class="rounded-lg bg-white p-6 shadow-sm">
    <h3 class="text-base font-semibold text-gray-900">Who to Call Today</h3>

    @if (empty($rows))
        <p class="mt-4 text-sm text-gray-400">No clients on your book yet.</p>
    @else
        <ul class="mt-4 divide-y divide-gray-100">
            @foreach ($rows as $row)
                <li class="py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <a href="{{ route('clients.show', $row['customer_id']) }}" class="font-medium text-indigo-600 hover:underline">
                                {{ $row['company_name'] }}
                            </a>
                            <p class="text-xs text-gray-500">{{ $row['reason'] }}</p>
                        </div>

                        @if ($aiEnabled && ! isset($suggestions[$row['customer_id']]))
                            <button type="button" wire:click="suggestTalkingPoint({{ $row['customer_id'] }})"
                                    wire:loading.attr="disabled" wire:target="suggestTalkingPoint({{ $row['customer_id'] }})"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                                <span wire:loading.remove wire:target="suggestTalkingPoint({{ $row['customer_id'] }})">✨ Suggest talking point</span>
                                <span wire:loading wire:target="suggestTalkingPoint({{ $row['customer_id'] }})">Thinking…</span>
                            </button>
                        @endif
                    </div>

                    @if (isset($suggestions[$row['customer_id']]))
                        <div class="mt-2 rounded-md border border-indigo-200 bg-indigo-50 p-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Suggested talking point</h4>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $suggestions[$row['customer_id']] }}</p>

                            @if (isset($feedback[$row['customer_id']]))
                                <p class="mt-1.5 text-xs text-gray-400">Thanks for the feedback.</p>
                            @else
                                <div class="mt-1.5 flex items-center gap-2 text-xs text-gray-400">
                                    <span>Was this useful?</span>
                                    <button type="button" wire:click="rate({{ $row['customer_id'] }}, 'up')" class="font-medium text-gray-500 hover:text-green-600">Helpful</button>
                                    <span aria-hidden="true">&middot;</span>
                                    <button type="button" wire:click="rate({{ $row['customer_id'] }}, 'down')" class="font-medium text-gray-500 hover:text-red-600">Not helpful</button>
                                </div>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($error)
            <p class="mt-2 text-xs text-red-600">{{ $error }}</p>
        @endif
    @endif
</div>
