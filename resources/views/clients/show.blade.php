<x-app-layout>
    <x-slot name="header">{{ $client->company_name }}</x-slot>

    <div class="max-w-7xl mx-auto space-y-6" x-data="{ tab: 'notes' }">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        {{-- Client header --}}
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-gray-900">{{ $client->company_name }}</h1>
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-green-100 text-green-800' => $client->status === \App\Enums\CustomerStatus::Active,
                            'bg-gray-100 text-gray-600' => $client->status === \App\Enums\CustomerStatus::Inactive,
                        ])>{{ $client->status->label() }}</span>
                    </div>
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">GSTIN:</span> {{ $client->gstin ?? '—' }}</div>
                        <div><span class="text-gray-400">Owner:</span> {{ $client->owner?->name ?? 'Unassigned' }}</div>
                        <div><span class="text-gray-400">Email:</span> {{ $client->email ?? '—' }}</div>
                        <div><span class="text-gray-400">Phone:</span> {{ $client->phone ?? '—' }}</div>
                        <div><span class="text-gray-400">Website:</span> {{ $client->website ?? '—' }}</div>
                        <div><span class="text-gray-400">State:</span> {{ $client->state ?? '—' }}</div>
                        <div class="sm:col-span-2">
                            <span class="text-gray-400">Address:</span>
                            {{ collect([$client->address_line1, $client->address_line2, $client->city, $client->state, $client->pincode])->filter()->join(', ') ?: '—' }}
                        </div>
                    </dl>
                    @if ($client->tags)
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($client->tags as $tag)
                                <span class="inline-flex rounded bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
                    @can('update', $client)
                        <a href="{{ route('clients.edit', $client) }}"
                           class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                            Edit
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Contacts --}}
        <livewire:contacts-manager :customer="$client" :can-manage="$canManage" />

        {{-- Tabbed timeline --}}
        <div class="rounded-lg bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6">
                <nav class="-mb-px flex gap-6 text-sm font-medium">
                    @foreach (['notes' => 'Notes', 'deals' => 'Deals', 'invoices' => 'Invoices', 'tickets' => 'Tickets'] as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                class="border-b-2 py-3">
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                <div x-show="tab === 'notes'">
                    <livewire:client-notes :customer="$client" :can-manage="$canManage" />
                </div>
                <div x-show="tab === 'deals'" x-cloak class="text-sm text-gray-400">Deals will appear here (Milestone 2).</div>
                <div x-show="tab === 'invoices'" x-cloak class="text-sm text-gray-400">Invoices will appear here (Milestone 3).</div>
                <div x-show="tab === 'tickets'" x-cloak class="text-sm text-gray-400">Tickets will appear here (Milestone 4).</div>
            </div>
        </div>
    </div>
</x-app-layout>
