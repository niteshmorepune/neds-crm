<x-app-layout>
    <x-slot name="header">{{ $client->company_name }}</x-slot>

    <div class="max-w-7xl mx-auto space-y-6" x-data="{ tab: 'services' }">
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
                        @if ($healthScore !== null)
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                                'bg-red-100 text-red-700' => $healthScore < 50,
                                'bg-amber-100 text-amber-700' => $healthScore >= 50 && $healthScore < 80,
                                'bg-green-100 text-green-700' => $healthScore >= 80,
                            ])>Health {{ $healthScore }}</span>
                        @endif
                    </div>
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">GSTIN:</span> {{ $client->gstin ?? '—' }}</div>
                        <div><span class="text-gray-400">Owner:</span> {{ $client->owner?->name ?? 'Unassigned' }}</div>
                        <div><span class="text-gray-400">Referred by:</span> {{ $client->referringPartner?->name ?? '—' }}</div>
                        <div><span class="text-gray-400">Email:</span> {{ $client->email ?? '—' }}</div>
                        <div><span class="text-gray-400">Phone:</span> {{ $client->phone ?? '—' }}</div>
                        @if ($client->alternate_phone)
                            <div><span class="text-gray-400">Alternate phone:</span> {{ $client->alternate_phone }}</div>
                        @endif
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

        {{-- Client 360° summary strip --}}
        @php
            $monthlyRecurring = $summary['recurring_by_frequency']['monthly'] ?? null;
            $quarterlyRecurring = $summary['recurring_by_frequency']['quarterly'] ?? null;
            $yearlyRecurring = $summary['recurring_by_frequency']['yearly'] ?? null;
        @endphp
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Monthly Recurring</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\Money::format($monthlyRecurring['value'] ?? 0) }}</p>
                <p class="mt-0.5 text-xs text-gray-400">
                    @if ($monthlyRecurring)
                        {{ $monthlyRecurring['count'] }} active monthly {{ Str::plural('service', $monthlyRecurring['count']) }}
                    @else
                        no active monthly services
                    @endif
                </p>
            </div>
            @if ($quarterlyRecurring)
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Quarterly Recurring</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\Money::format($quarterlyRecurring['value']) }}<span class="text-xs font-normal text-gray-400">/qtr</span></p>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $quarterlyRecurring['count'] }} active quarterly {{ Str::plural('service', $quarterlyRecurring['count']) }}</p>
                </div>
            @endif
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Yearly Recurring</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\Money::format($yearlyRecurring['value'] ?? 0) }}<span class="text-xs font-normal text-gray-400">/yr</span></p>
                <p class="mt-0.5 text-xs text-gray-400">
                    @if ($yearlyRecurring)
                        {{ $yearlyRecurring['count'] }} active yearly {{ Str::plural('service', $yearlyRecurring['count']) }}
                    @else
                        no active yearly services
                    @endif
                </p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Next Renewal</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ $summary['next_renewal']?->format('d M Y') ?? '—' }}</p>
                <p class="mt-0.5 text-xs text-gray-400">soonest active template's next bill date</p>
            </div>
            @if ($canViewInvoices)
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total Revenue</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\Money::format($summary['total_revenue']) }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">all invoices, lifetime</p>
                </div>
                <button type="button" @click="tab = 'invoices'"
                        class="rounded-lg bg-white p-4 text-left shadow-sm hover:ring-1 hover:ring-indigo-200 transition-shadow">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Outstanding</p>
                    <p @class([
                        'mt-1 text-xl font-semibold',
                        'text-red-700' => $summary['outstanding'] > 0,
                        'text-gray-900' => $summary['outstanding'] === 0,
                    ])>{{ \App\Support\Money::format($summary['outstanding']) }}</p>
                    <p class="mt-0.5 text-xs text-indigo-600">view invoices →</p>
                </button>
            @endif
            @if ($canViewAdvances)
                <button type="button" @click="tab = 'advances'"
                        class="rounded-lg bg-white p-4 text-left shadow-sm hover:ring-1 hover:ring-indigo-200 transition-shadow">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Unapplied Advances</p>
                    <p @class([
                        'mt-1 text-xl font-semibold',
                        'text-blue-700' => $summary['unapplied_advances'] > 0,
                        'text-gray-900' => $summary['unapplied_advances'] === 0,
                    ])>{{ \App\Support\Money::format($summary['unapplied_advances']) }}</p>
                    <p class="mt-0.5 text-xs text-indigo-600">view details →</p>
                </button>
            @endif
        </div>

        {{-- Tabbed timeline --}}
        <div class="rounded-lg bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6">
                <nav class="-mb-px flex gap-6 text-sm font-medium">
                    @php
                        $tabs = ['services' => 'Services', 'requirements' => 'Requirements', 'assets' => 'Assets', 'notes' => 'Notes', 'calls' => 'Calls', 'deals' => 'Deals', 'invoices' => 'Invoices', 'tickets' => 'Tickets', 'links' => 'Links'];
                        if ($canViewAdvances) {
                            $tabs['advances'] = 'Advances';
                        }
                    @endphp
                    @foreach ($tabs as $key => $label)
                        <button type="button" @click="tab = '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                class="border-b-2 py-3">{{ $label }}@if (isset($tabCounts[$key])) ({{ $tabCounts[$key] }})@endif</button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                <div x-show="tab === 'services'">
                    @include('clients._services_tab', ['client' => $client, 'canViewInvoices' => $canViewInvoices, 'reselleredRecurring' => $reselleredRecurring, 'canManageServices' => $canManageServices, 'staff' => $staff])
                </div>
                <div x-show="tab === 'requirements'" x-cloak>
                    <livewire:client-requirements :customer="$client" :can-manage="$canManageServices" />
                </div>
                <div x-show="tab === 'assets'" x-cloak>
                    <livewire:client-assets :customer="$client" :can-manage="$canManageServices" />
                </div>
                <div x-show="tab === 'notes'" x-cloak>
                    <livewire:client-notes :customer="$client" :can-manage="$canManage" />
                </div>
                <div x-show="tab === 'calls'" x-cloak>
                    <div class="mb-3 flex justify-end">
                        <a href="{{ route('calls.create', ['customer_id' => $client->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Log a call</a>
                    </div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($client->callLogs as $call)
                            <li class="py-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-700">{{ $call->direction->label() }} · {{ $call->outcome->label() }}{{ $call->duration_minutes ? " · {$call->duration_minutes}m" : '' }}</span>
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs text-gray-400">{{ $call->called_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }} · {{ $call->user?->name }}</span>
                                        @can('delete', $call)
                                            <form method="POST" action="{{ route('calls.destroy', $call) }}" onsubmit="return confirm('Delete this call log? This cannot be undone.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-600">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                                @if ($call->notes)<p class="mt-1 text-gray-500">{{ $call->notes }}</p>@endif
                            </li>
                        @empty
                            <li class="py-2 text-gray-400">No calls logged.</li>
                        @endforelse
                    </ul>

                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <p class="mb-2 text-xs font-medium text-gray-500">Meet notes</p>
                        <livewire:meeting-import :record="$client" :can-manage="$canManageMeetings" />
                    </div>
                </div>
                <div x-show="tab === 'deals'" x-cloak>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($client->deals as $deal)
                            <li class="flex items-center justify-between py-2">
                                <a href="{{ route('deals.show', $deal) }}" class="font-medium text-indigo-600 hover:underline">{{ $deal->title }}</a>
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500">{{ $deal->stage->label() }} · {{ $deal->owner?->name ?? 'Unassigned' }}</span>
                                    @can('update', $deal)
                                        <a href="{{ route('deals.show', $deal) }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                                    @endcan
                                    @can('delete', $deal)
                                        <form method="POST" action="{{ route('deals.destroy', $deal) }}" onsubmit="return confirm('Delete this deal? This cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-600">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </li>
                        @empty
                            <li class="py-2 text-gray-400">No deals yet.</li>
                        @endforelse
                    </ul>
                </div>
                <div x-show="tab === 'invoices'" x-cloak>
                    @can('create', \App\Models\Invoice::class)
                        <div class="mb-3 flex justify-end">
                            <a href="{{ route('invoices.create', ['customer_id' => $client->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Log Invoice</a>
                        </div>
                    @endcan
                    @if ($canViewInvoices)
                        @php
                            // Includes reseller-billed invoices (customer_id points at
                            // the billing customer, not this client) found via their
                            // Project's project_id — see CustomerController::show().
                            $allClientInvoices = $client->invoices->concat($reselleredInvoices)->sortByDesc('issue_date');
                        @endphp
                        <ul class="divide-y divide-gray-100 text-sm">
                            @forelse ($allClientInvoices as $invoice)
                                <li class="flex items-center justify-between py-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->invoice_number }}</a>
                                    <span class="text-gray-500">
                                        @if ($invoice->customer_id !== $client->id)
                                            <span class="text-amber-700">Billed via {{ $invoice->customer?->company_name ?? 'Client removed' }}</span> ·
                                        @endif
                                        {{ \App\Support\Money::format($invoice->total) }} · {{ $invoice->status->label() }}
                                    </span>
                                </li>
                            @empty
                                <li class="py-2 text-gray-400">No invoices yet.</li>
                            @endforelse
                        </ul>
                    @else
                        <p class="text-sm text-gray-400">You don't have access to invoices.</p>
                    @endif
                </div>
                <div x-show="tab === 'tickets'" x-cloak>
                    @php
                        $ratings = $client->tickets->pluck('satisfactionRating')->filter()->sortByDesc('created_at');
                    @endphp
                    @if ($ratings->isNotEmpty())
                        <div class="mb-4 rounded-lg bg-gray-50 p-4">
                            <p class="text-xs font-medium text-gray-500">
                                Client feedback — avg {{ round($ratings->avg('rating'), 1) }}/5 from {{ $ratings->count() }} rating{{ $ratings->count() === 1 ? '' : 's' }}
                            </p>
                            <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                @foreach ($ratings->take(3) as $rating)
                                    @if ($rating->comment)
                                        <li>"{{ $rating->comment }}" — {{ str_repeat('★', $rating->rating) }}{{ str_repeat('☆', 5 - $rating->rating) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <ul class="divide-y divide-gray-100 text-sm">
                        @forelse ($client->tickets as $ticket)
                            <li class="flex items-center justify-between py-2">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:underline">{{ $ticket->subject }}</a>
                                <span class="text-gray-500">{{ $ticket->status->label() }} · {{ $ticket->assignee?->name ?? 'Unassigned' }}</span>
                            </li>
                        @empty
                            <li class="py-2 text-gray-400">No tickets yet.</li>
                        @endforelse
                    </ul>
                </div>
                <div x-show="tab === 'links'" x-cloak>
                    <livewire:important-links-manager :customer="$client" :can-manage="$canManageLinks" />
                </div>
                @if ($canViewAdvances)
                    <div x-show="tab === 'advances'" x-cloak>
                        @include('clients._advances', ['client' => $client])
                    </div>
                @endif
            </div>
        </div>

        {{-- Contacts --}}
        <livewire:contacts-manager :customer="$client" :can-manage="$canManage" />
    </div>
</x-app-layout>
