<x-app-layout>
    <x-slot name="header">Clients</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="GET" class="space-y-3">
            {{-- Row 1: page-scoped search (distinct from the global omnisearch
                 in the top bar — this one only searches clients and drives
                 this list/export/pagination) + primary actions. --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="relative max-w-md flex-1 min-w-[220px]">
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                           placeholder="Search company, email, GSTIN"
                           class="w-full rounded-md border-gray-300 pr-9 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <button type="submit" aria-label="Search"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    @can('export', \App\Models\Customer::class)
                        <a href="{{ route('clients.export', request()->query()) }}"
                           class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Export CSV
                        </a>
                    @endcan
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

            {{-- Row 2: filters, each auto-applying on change — no separate
                 Filter button. All fields share this one form, so changing
                 one select resubmits with the search text and every other
                 currently-selected filter intact. --}}
            <div class="flex flex-wrap items-center gap-2">
                <select name="status" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="all" @selected($statusFilter === 'all')>All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected($statusFilter === $s->value)>
                            {{ $s->label() }}
                        </option>
                    @endforeach
                </select>

                <select name="owner_id" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All owners</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $owner->id)>
                            {{ $owner->name }}
                        </option>
                    @endforeach
                </select>

                <select name="referring_partner_id" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All referring partners</option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->id }}" @selected((string) ($filters['referring_partner_id'] ?? '') === (string) $partner->id)>
                            {{ $partner->name }}
                        </option>
                    @endforeach
                </select>

                <select name="state" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All states</option>
                    @foreach ($states as $state)
                        <option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>
                    @endforeach
                </select>

                <select name="city" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All cities</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                    @endforeach
                </select>

                <select name="sort" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="newest" @selected($sort === 'newest')>Newest first</option>
                    <option value="name" @selected($sort === 'name')>Company name (A–Z)</option>
                    <option value="oldest" @selected($sort === 'oldest')>Date of entry (oldest first)</option>
                    <option value="location" @selected($sort === 'location')>Location (State, City)</option>
                </select>

                {{-- "Active" is the default status shown with no query params
                     at all (see CustomerController::index()), so it doesn't
                     count as an active filter on its own — anything else
                     (including the explicit "All statuses" option) does. --}}
                @if (($filters['search'] ?? '') || $statusFilter !== \App\Enums\CustomerStatus::Active->value || ($filters['owner_id'] ?? '') || ($filters['referring_partner_id'] ?? '') || ($filters['state'] ?? '') || ($filters['city'] ?? ''))
                    <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear filters</a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Company</th>
                        <th class="px-4 py-3">Services</th>
                        <th class="px-4 py-3">Primary contact</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date of entry</th>
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
                            <td class="px-4 py-3">
                                @php($services = $customer->activeServiceNames())
                                @if ($services->isEmpty())
                                    <span class="text-gray-400">—</span>
                                @else
                                    <div x-data="{ expanded: false }">
                                        <div x-show="!expanded" class="flex flex-wrap items-center gap-1">
                                            @foreach ($services->take(2) as $name)
                                                <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $name }}</span>
                                            @endforeach
                                            @if ($services->count() > 2)
                                                <button type="button" @click="expanded = true"
                                                        title="{{ $services->implode(', ') }}"
                                                        class="text-xs font-medium text-indigo-600 hover:underline">
                                                    +{{ $services->count() - 2 }} more
                                                </button>
                                            @endif
                                        </div>
                                        @if ($services->count() > 2)
                                            <div x-show="expanded" x-cloak class="flex flex-wrap items-center gap-1">
                                                @foreach ($services as $name)
                                                    <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $name }}</span>
                                                @endforeach
                                                <button type="button" @click="expanded = false" class="text-xs text-gray-400 hover:underline">Show less</button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $customer->primaryContact?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ collect([$customer->city, $customer->state])->filter()->join(', ') ?: '—' }}
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
                            <td class="px-4 py-3 text-gray-600">{{ $customer->created_at->timezone(config('app.display_timezone', 'Asia/Kolkata'))->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('clients.show', $customer) }}" class="text-gray-500 hover:text-gray-700">View</a>
                                @can('update', $customer)
                                    <a href="{{ route('clients.edit', $customer) }}" class="ml-3 text-gray-500 hover:text-gray-700">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-400">No clients found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $customers->links() }}</div>
    </div>
</x-app-layout>
