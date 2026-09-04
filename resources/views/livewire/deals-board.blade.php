<div x-data="{ dragId: null, pendingLostDealId: null }"
     x-on:deal-move-blocked.window="pendingLostDealId = null; alert('That deal is Won or Lost and can\'t be moved.')">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Sales Pipeline</h1>
            <p class="mt-0.5 text-sm text-gray-500">
                Drag deals through their stages here. Full KPIs, trends & targets →
                <a href="{{ route('sales-dashboard.index') }}" class="font-medium text-indigo-600 hover:underline">Sales Dashboard</a>
            </p>
        </div>
        <div class="flex items-center gap-4">
            @can('create', \App\Models\Deal::class)
                <button wire:click="$toggle('showAddForm')"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    {{ $showAddForm ? 'Close' : 'Add deal' }}
                </button>
            @endcan
        </div>
    </div>

    @if ($showAddForm)
        <div class="mb-4 grid grid-cols-1 gap-4 rounded-lg bg-white p-4 shadow-sm md:grid-cols-5">
            <div>
                <x-input-label value="Client *" />
                <select wire:model="customer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Select client</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->company_name }}</option>
                    @endforeach
                </select>
                @error('customer_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Title *" />
                <x-text-input wire:model="title" type="text" class="mt-1 block w-full" />
                @error('title') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Service" />
                <select wire:model="service_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">—</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">Covers two services? Pick the main one and name the other in the title.</p>
            </div>
            <div>
                <x-input-label value="Value (₹) *" />
                <x-text-input wire:model="value" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <p class="mt-1 text-xs text-gray-400">Amount before GST — not the quotation/invoice total. This feeds Sales Incentive and target reports directly.</p>
                @error('value') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-input-label value="Owner" />
                <select wire:model="owner_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Unassigned</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-5">
                <x-primary-button wire:click="createDeal" type="button">Create deal</x-primary-button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($columns as $stage)
            @php
                $stageColors = match ($stage) {
                    \App\Enums\DealStage::New => ['border' => 'border-t-slate-400', 'badge' => 'bg-slate-100 text-slate-700'],
                    \App\Enums\DealStage::Contacted => ['border' => 'border-t-blue-400', 'badge' => 'bg-blue-100 text-blue-700'],
                    \App\Enums\DealStage::Proposal => ['border' => 'border-t-purple-400', 'badge' => 'bg-purple-100 text-purple-700'],
                    \App\Enums\DealStage::Negotiation => ['border' => 'border-t-amber-400', 'badge' => 'bg-amber-100 text-amber-700'],
                    \App\Enums\DealStage::Won => ['border' => 'border-t-green-400', 'badge' => 'bg-green-100 text-green-700'],
                    \App\Enums\DealStage::Lost => ['border' => 'border-t-red-400', 'badge' => 'bg-red-100 text-red-700'],
                };
            @endphp
            <div class="flex flex-col rounded-lg border-t-4 {{ $stageColors['border'] }} bg-gray-50 p-3"
                 x-on:dragover.prevent
                 x-on:drop.prevent="if (dragId) { @if ($stage === \App\Enums\DealStage::Lost) pendingLostDealId = dragId; $wire.suggestLostReason(dragId) @else $wire.moveDeal(dragId, '{{ $stage->value }}') @endif; dragId = null }">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">{{ $stage->label() }}</h3>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $stageColors['badge'] }}">
                        {{ ($dealsByStage[$stage->value] ?? collect())->count() }}
                    </span>
                </div>

                <div class="space-y-2">
                    @forelse ($dealsByStage[$stage->value] ?? [] as $deal)
                        <div class="rounded-md border border-gray-200 bg-white p-3 shadow-sm"
                             draggable="true"
                             x-on:dragstart="dragId = {{ $deal->id }}">
                            <a href="{{ route('deals.show', $deal) }}" class="text-sm font-medium text-indigo-600 hover:underline">
                                {{ $deal->title }}
                            </a>
                            <div class="mt-1 text-xs text-gray-500">{{ $deal->customer?->company_name ?? 'Client removed' }}</div>
                            <div class="mt-1 text-xs font-medium text-gray-700">
                                {{ \App\Support\Money::format($deal->value) }}
                                @if ($deal->confidence !== null)
                                    <span class="ml-1 font-normal text-gray-400">🎯 {{ $deal->confidence }}/10</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-400">
                                {{ $deal->service?->name ?? 'No service' }} · {{ $deal->owner?->name ?? 'Unassigned' }}
                            </div>
                            @unless ($stage->isTerminal())
                                @php $daysInStage = (int) floor($deal->stage_changed_at?->diffInDays(now()) ?? 0); @endphp
                                <div class="mt-1 text-xs {{ $daysInStage > 10 ? 'font-medium text-red-500' : 'text-gray-400' }}">
                                    @if ($daysInStage > 10) ⚠ @endif{{ $daysInStage }} {{ Str::plural('day', $daysInStage) }} in stage
                                </div>
                            @endunless
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-gray-300">Drop deals here</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div x-show="pendingLostDealId !== null" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4">
        <div class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
            <h3 class="text-sm font-semibold text-gray-900">Why was this deal lost?</h3>

            <p wire:loading wire:target="suggestLostReason" class="mt-3 text-xs text-gray-400">
                Checking notes for a suggestion&hellip;
            </p>

            <div wire:loading.remove wire:target="suggestLostReason">
                @if ($lostReasonRationale)
                    <p class="mt-3 rounded-md bg-indigo-50 px-3 py-2 text-xs text-indigo-700">
                        ✨ {{ $lostReasonRationale }}
                    </p>
                @elseif ($suggestedLostReason === null)
                    <p class="mt-3 text-xs text-gray-400">No suggestion available — pick the one that fits.</p>
                @endif

                <div class="mt-3 space-y-1.5">
                    @foreach (\App\Enums\DealLostReason::cases() as $reason)
                        <button type="button"
                                x-on:click="$wire.moveDeal(pendingLostDealId, 'lost', '{{ $reason->value }}'); pendingLostDealId = null"
                                @class([
                                    'block w-full rounded-md border px-3 py-2 text-left text-sm hover:bg-red-50',
                                    'border-indigo-300 bg-indigo-50 text-indigo-900 hover:border-indigo-400' => $suggestedLostReason === $reason->value,
                                    'border-gray-200 text-gray-700 hover:border-red-300' => $suggestedLostReason !== $reason->value,
                                ])>
                            {{ $reason->label() }}
                            @if ($suggestedLostReason === $reason->value)
                                <span class="ml-1 text-xs text-indigo-500">✨ Suggested</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button" x-on:click="pendingLostDealId = null" class="mt-3 text-xs text-gray-400 hover:text-gray-600">Cancel</button>
        </div>
    </div>
</div>
