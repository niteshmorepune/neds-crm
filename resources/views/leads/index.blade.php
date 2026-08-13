<x-app-layout>
    <x-slot name="header">Lead Generation</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @if ($filters['month'] ?? null)
            <div class="flex items-center justify-between rounded-md bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-800">
                <span>Showing leads captured in {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $filters['month'])->format('F Y') }} only.</span>
                <a href="{{ route('leads.index', array_diff_key($filters, ['month' => null])) }}" class="font-medium hover:underline">Clear →</a>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total Leads</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $statusCounts['total'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">New</p>
                <p class="mt-1 text-2xl font-semibold text-blue-700">{{ $statusCounts['new'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Contacted</p>
                <p class="mt-1 text-2xl font-semibold text-yellow-700">{{ $statusCounts['contacted'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Qualified</p>
                <p class="mt-1 text-2xl font-semibold text-yellow-700">{{ $statusCounts['qualified'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Converted</p>
                <p class="mt-1 text-2xl font-semibold text-green-700">{{ $statusCounts['converted'] }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Lost</p>
                <p class="mt-1 text-2xl font-semibold text-gray-600">{{ $statusCounts['lost'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, company, email"
                       class="rounded-md border-gray-300 text-sm shadow-sm" />
                <select name="source" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\LeadStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select name="service_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All services</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->id)>{{ $service->name }}</option>
                    @endforeach
                </select>
                <select name="owner_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All owners</option>
                    @foreach ($owners as $owner)
                        <option value="{{ $owner->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>

            @can('create', \App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Add Lead</a>
            @endcan
        </div>

        @if ($canBulkReassign && $filterOwner)
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4" x-data>
                @if ($bulkReassignOpenCount > 0)
                    <form method="POST" action="{{ route('leads.bulk-reassign') }}" class="flex flex-wrap items-end gap-3"
                          onsubmit="return confirm('Reassign all {{ $bulkReassignOpenCount }} open lead(s) from {{ $filterOwner->name }}? This can be undone the same way later, but not automatically.')">
                        @csrf
                        <input type="hidden" name="from_user_id" value="{{ $filterOwner->id }}">
                        <p class="text-sm text-indigo-900 self-center">
                            <span class="font-medium">{{ $filterOwner->name }}</span> has {{ $bulkReassignOpenCount }} open lead(s). Reassign all to:
                        </p>
                        <select name="to_user_id" class="rounded-md border-gray-300 text-sm shadow-sm" required>
                            <option value="">—</option>
                            @foreach ($bulkReassignTargets as $target)
                                @continue($target->id === $filterOwner->id)
                                <option value="{{ $target->id }}">{{ $target->name }}</option>
                            @endforeach
                        </select>
                        <select name="reason" class="rounded-md border-gray-300 text-sm shadow-sm" required>
                            @foreach ($reassignReasons as $reasonOption)
                                <option value="{{ $reasonOption->value }}">{{ $reasonOption->label() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Reassign All</button>
                    </form>
                @else
                    <p class="text-sm text-indigo-900">{{ $filterOwner->name }} has no open leads to reassign.</p>
                @endif
            </div>
        @endif

        @can('merge', \App\Models\Lead::class)
            <form method="GET" action="{{ route('leads.merge.show') }}" x-data="{ checked: [] }" id="merge-form">
        @endcan

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        @can('merge', \App\Models\Lead::class)
                            <th class="w-8 px-4 py-3"></th>
                        @endcan
                        <th class="px-4 py-3">Lead</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Est. value</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Latest Note</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($leads as $lead)
                        <tr class="hover:bg-gray-50">
                            @can('merge', \App\Models\Lead::class)
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $lead->id }}" form="merge-form"
                                           x-model="checked" :disabled="checked.length >= 2 && ! checked.includes('{{ $lead->id }}')"
                                           class="rounded border-gray-300 text-indigo-600" />
                                </td>
                            @endcan
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('leads.show', $lead) }}" class="font-medium text-indigo-600 hover:underline">{{ $lead->name }}</a>
                                    <x-lead-score :lead="$lead" />
                                </div>
                                <div class="text-xs text-gray-400">{{ $lead->company ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $lead->source->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $lead->service?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ \App\Support\Money::format($lead->estimated_value) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $lead->owner?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-blue-100 text-blue-800' => $lead->status === \App\Enums\LeadStatus::New,
                                    'bg-yellow-100 text-yellow-800' => in_array($lead->status, [\App\Enums\LeadStatus::Contacted, \App\Enums\LeadStatus::Qualified]),
                                    'bg-green-100 text-green-800' => $lead->status === \App\Enums\LeadStatus::Converted,
                                    'bg-gray-100 text-gray-600' => $lead->status === \App\Enums\LeadStatus::Lost,
                                ])>{{ $lead->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                @if ($lead->latestNote)
                                    <span class="text-gray-600" title="{{ $lead->latestNote->body }}">{{ \Illuminate\Support\Str::limit($lead->latestNote->body, 60) }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('leads.show', $lead) }}" class="text-gray-500 hover:text-gray-700">View</a>
                            </td>
                        </tr>
                    @empty
                        @php($colspan = 8)
                        @can('merge', \App\Models\Lead::class) @php($colspan = 9) @endcan
                        <tr><td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-gray-400">No leads found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('merge', \App\Models\Lead::class)
            <div class="flex items-center gap-3">
                <button type="submit" form="merge-form" :disabled="checked.length !== 2"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-40">
                    Merge Selected
                </button>
                <p class="text-xs text-gray-400" x-show="checked.length !== 2">Select exactly 2 leads to merge duplicates.</p>
            </div>
            </form>
        @endcan

        <div>{{ $leads->links() }}</div>
    </div>
</x-app-layout>
