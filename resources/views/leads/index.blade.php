<x-app-layout>
    <x-slot name="header">Lead Generation</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

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
                <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>

            @can('create', \App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Add Lead</a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Lead</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Est. value</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($leads as $lead)
                        <tr class="hover:bg-gray-50">
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
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('leads.show', $lead) }}" class="text-gray-500 hover:text-gray-700">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No leads found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $leads->links() }}</div>
    </div>
</x-app-layout>
