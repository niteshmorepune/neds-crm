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
                <x-input-label value="Value (₹)" />
                <x-text-input wire:model="value" type="number" step="0.01" min="0" class="mt-1 block w-full" />
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
            <div class="flex flex-col rounded-lg bg-gray-50 p-3"
                 x-on:dragover.prevent
                 x-on:drop.prevent="if (dragId) { $wire.moveDeal(dragId, '{{ $stage->value }}'); dragId = null }">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">{{ $stage->label() }}</h3>
                    <span class="text-xs text-gray-400">{{ ($dealsByStage[$stage->value] ?? collect())->count() }}</span>
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
