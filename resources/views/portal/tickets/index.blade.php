<x-portal-app-layout header="Your Tickets">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Track your support requests below.</p>
        <div class="flex items-center gap-3">
            @if (config('company.whatsapp'))
                <x-whatsapp-button label="Chat on WhatsApp" />
            @endif
            <a href="{{ route('portal.tickets.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors shadow-sm">
                + New Ticket
            </a>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="sm:hidden space-y-3">
        @forelse ($tickets as $ticket)
            @php
                $statusColor = match($ticket->status->value ?? '') {
                    'open'        => 'bg-blue-100 text-blue-700',
                    'in_progress' => 'bg-indigo-100 text-indigo-700',
                    'resolved', 'closed' => 'bg-green-100 text-green-700',
                    default       => 'bg-gray-100 text-gray-600',
                };
                $priorityColor = match($ticket->priority->value ?? '') {
                    'high', 'critical' => 'text-red-600',
                    'normal'           => 'text-amber-600',
                    default            => 'text-gray-500',
                };
            @endphp
            <a href="{{ route('portal.tickets.show', $ticket->id) }}"
               class="block rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow-md transition-all">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <p class="font-semibold text-sm text-gray-900">{{ $ticket->subject }}</p>
                    <span class="shrink-0 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                        {{ $ticket->status->label() }}
                    </span>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span class="{{ $priorityColor }} font-medium">{{ $ticket->priority->label() }}</span>
                    <span>·</span>
                    <span>{{ $ticket->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</span>
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
                <p class="text-sm text-gray-400">No tickets yet. Raise one if you need help.</p>
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden sm:block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3">Subject</th>
                    <th class="px-5 py-3">Priority</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Raised on</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($tickets as $ticket)
                    @php
                        $statusColor = match($ticket->status->value ?? '') {
                            'open'               => 'bg-blue-100 text-blue-700',
                            'in_progress'        => 'bg-indigo-100 text-indigo-700',
                            'resolved', 'closed' => 'bg-green-100 text-green-700',
                            default              => 'bg-gray-100 text-gray-600',
                        };
                        $priorityColor = match($ticket->priority->value ?? '') {
                            'high', 'critical' => 'text-red-600 font-semibold',
                            'normal'           => 'text-amber-600',
                            default            => 'text-gray-500',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('portal.tickets.show', $ticket->id) }}"
                               class="font-medium text-indigo-600 hover:underline">{{ $ticket->subject }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-sm {{ $priorityColor }}">{{ $ticket->priority->label() }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                                {{ $ticket->status->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $ticket->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-gray-400">No tickets yet. Raise one if you need help.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-portal-app-layout>
