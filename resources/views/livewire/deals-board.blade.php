<div x-data="{ dragId: null }"
     x-on:deal-move-blocked.window="alert('That deal is Won or Lost and can\'t be moved.')">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Sales Pipeline</h1>
        @can('create', \App\Models\Deal::class)
            <button wire:click="$toggle('showAddForm')"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                {{ $showAddForm ? 'Close' : 'Add deal' }}
            </button>
        @endcan
    </div>

    @php
        $kpiCards = [
            ['label' => 'Open pipeline', 'value' => \App\Support\Money::format($kpis['open_pipeline_value'])],
            ['label' => 'Weighted forecast', 'value' => \App\Support\Money::format($kpis['weighted_forecast'])],
            ['label' => 'Won this month', 'value' => \App\Support\Money::format($kpis['won_this_month_value'])],
            ['label' => 'Won this FY', 'value' => \App\Support\Money::format($kpis['won_this_fy_value'])],
            ['label' => 'Win rate', 'value' => $kpis['win_rate'] !== null ? $kpis['win_rate'].'%' : '—'],
            ['label' => 'Avg deal size', 'value' => \App\Support\Money::format($kpis['avg_deal_size'])],
            ['label' => 'Avg sales cycle', 'value' => $kpis['avg_sales_cycle_days'] !== null ? $kpis['avg_sales_cycle_days'].' days' : '—'],
        ];
    @endphp
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
        @foreach ($kpiCards as $card)
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ $card['value'] }}</p>
            </div>
        @endforeach
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
            </div>
            <div>
                <x-input-label value="Value (₹) *" />
                <x-text-input wire:model="value" type="number" step="0.01" min="0" class="mt-1 block w-full" />
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
                 x-on:drop.prevent="if (dragId) { $wire.moveDeal(dragId, '{{ $stage->value }}'); dragId = null }">
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
                            <div class="mt-1 text-xs font-medium text-gray-700">{{ \App\Support\Money::format($deal->value) }}</div>
                            <div class="mt-1 text-xs text-gray-400">
                                {{ $deal->service?->name ?? 'No service' }} · {{ $deal->owner?->name ?? 'Unassigned' }}
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-gray-300">Drop deals here</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
