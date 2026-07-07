<x-app-layout>
    <x-slot name="header">Clients</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search company, email, GSTIN"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="all" @selected($statusFilter === 'all')>All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected($statusFilter === $s->value)>
                            {{ $s->label() }}
                        </option>
                    @endforeach
                </select>

                <select name="owner_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All owners</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $owner->id)>
                            {{ $owner->name }}
                        </option>
                    @endforeach
                </select>

                <select name="referring_partner_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All referring partners</option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->id }}" @selected((string) ($filters['referring_partner_id'] ?? '') === (string) $partner->id)>
                            {{ $partner->name }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    Filter
                </button>
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ route('clients.import') }}"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Import CSV
                </a>
                @can('create', \App\Models\Customer::class)
                    <a href="{{ route('clients.create') }}"
                       class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        Add Client
                    </a>
                @endcan
            </div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Primary contact</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('clients.show', $customer) }}" class="font-medium text-indigo-600 hover:underline">
                                    {{ $customer->company_name }}
                                </a>
                                <div class="text-xs text-gray-400">
                                    {{ $customer->gstin ?? 'No GSTIN' }} · {{ $customer->contacts_count }} contact(s)
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $customer->primaryContact?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->owner?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $customer->status === \App\Enums\CustomerStatus::Active,
                                    'bg-yellow-100 text-yellow-800' => $customer->status === \App\Enums\CustomerStatus::Prospect,
                                    'bg-gray-100 text-gray-600' => $customer->status === \App\Enums\CustomerStatus::Inactive,
                                ])>{{ $customer->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('clients.show', $customer) }}" class="text-gray-500 hover:text-gray-700">View</a>
                                @can('update', $customer)
                                    <a href="{{ route('clients.edit', $customer) }}" class="ml-3 text-gray-500 hover:text-gray-700">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">No clients found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $customers->links() }}</div>
    </div>
</x-app-layout>
