<x-app-layout>
    <x-slot name="header">Tickets</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-1 text-sm text-gray-600">
                    <input type="checkbox" name="mine" value="1" @checked(! empty($filters['mine'])) onchange="this.form.submit()" class="rounded border-gray-300 text-indigo-600" /> Assigned to me
                </label>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All priorities</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>
            @can('create', \App\Models\Ticket::class)
                <a href="{{ route('tickets.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New Ticket</a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Assignee</th>
                        <th class="px-4 py-3">SLA due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($tickets as $ticket)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:underline">{{ $ticket->subject }}</a></td>
                            <td class="px-4 py-3 text-gray-600">{{ $ticket->customer->company_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $ticket->priority->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $ticket->status->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 {{ $ticket->isSlaBreached() ? 'font-medium text-red-600' : 'text-gray-600' }}">
                                {{ $ticket->sla_due_at?->timezone(config('app.display_timezone'))->format('d M, g:i A') ?? 'â€”' }}
                                {{ $ticket->isSlaBreached() ? 'Â· breached' : '' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No tickets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $tickets->links() }}</div>
    </div>
</x-app-layout>

