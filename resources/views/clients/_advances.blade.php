@php
    $outstandingAdvances = $client->clientAdvances->whereIn('status', [\App\Enums\ClientAdvanceStatus::Outstanding, \App\Enums\ClientAdvanceStatus::PartiallyApplied]);
    $totalRemaining = $outstandingAdvances->sum(fn ($a) => $a->remaining());
@endphp

<div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4" x-data="{ recording: false }">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-900">Client Advances</p>
            <p class="text-xs text-gray-500">Money received with no quotation/invoice yet — apply it once one exists.</p>
        </div>
        <div class="text-right">
            <p class="text-lg font-semibold {{ $totalRemaining > 0 ? 'text-blue-700' : 'text-gray-900' }}">{{ \App\Support\Money::format($totalRemaining) }}</p>
            <p class="text-xs text-gray-400">unapplied</p>
        </div>
    </div>

    @if ($client->clientAdvances->isNotEmpty())
        <ul class="mt-3 divide-y divide-gray-200 text-sm">
            @foreach ($client->clientAdvances->sortByDesc('received_on') as $advance)
                <li class="flex items-center justify-between py-2">
                    <div>
                        <span class="font-medium text-gray-700">{{ \App\Support\Money::format($advance->amount) }}</span>
                        <span class="text-gray-400">· {{ $advance->received_on->format('d M Y') }} · {{ $advance->mode->label() }}</span>
                        @if ($advance->reference)<span class="text-gray-400">· {{ $advance->reference }}</span>@endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($advance->status !== \App\Enums\ClientAdvanceStatus::Cancelled)
                            <span class="text-xs text-gray-500">{{ \App\Support\Money::format($advance->remaining()) }} remaining</span>
                        @endif
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $advance->status->badgeClass() }}">{{ $advance->status->label() }}</span>
                        @can('cancel', $advance)
                            @if ($advance->amount_applied === 0 && $advance->status !== \App\Enums\ClientAdvanceStatus::Cancelled)
                                <form method="POST" action="{{ route('advances.cancel', $advance) }}" onsubmit="return confirm('Cancel this advance? It will no longer be available to apply.')">
                                    @csrf
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-600">Cancel</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @can('create', \App\Models\ClientAdvance::class)
        <div class="mt-3">
            <button type="button" x-show="!recording" @click="recording = true" class="text-sm font-medium text-indigo-600 hover:underline">+ Record Advance</button>

            <form x-show="recording" x-cloak method="POST" action="{{ route('advances.store', $client) }}" class="mt-2 space-y-2 rounded-md bg-white p-3 ring-1 ring-gray-200">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-input-label for="advance_amount" value="Amount (₹)" />
                        <x-text-input id="advance_amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" />
                    </div>
                    <div>
                        <x-input-label for="advance_received_on" value="Date" />
                        <x-text-input id="advance_received_on" name="received_on" type="date" class="mt-1 block w-full" :value="old('received_on', now()->toDateString())" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <x-input-label for="advance_mode" value="Mode" />
                        <select id="advance_mode" name="mode" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @foreach (\App\Enums\PaymentMode::cases() as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="advance_reference" value="Reference (optional)" />
                        <x-text-input id="advance_reference" name="reference" type="text" class="mt-1 block w-full" :value="old('reference')" />
                    </div>
                </div>
                <div>
                    <x-input-label for="advance_note" value="Note (optional)" />
                    <textarea id="advance_note" name="note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">{{ old('note') }}</textarea>
                </div>
                @error('amount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <button type="button" @click="recording = false" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <x-primary-button class="text-xs">Record Advance</x-primary-button>
                </div>
            </form>
        </div>
    @endcan
</div>
