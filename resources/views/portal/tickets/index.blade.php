<x-portal-app-layout header="My Tickets">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Track your support requests below, or reach us instantly on WhatsApp.</p>
        <div class="flex items-center gap-3">
            <x-whatsapp-button label="Chat on WhatsApp" />
            <a href="{{ route('portal.tickets.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New ticket</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr><th class="px-4 py-3">Subject</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Raised</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($tickets as $ticket)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><a href="{{ route('portal.tickets.show', $ticket->id) }}" class="font-medium text-indigo-600 hover:underline">{{ $ticket->subject }}</a></td>
                        <td class="px-4 py-3 text-gray-600">{{ $ticket->priority->label() }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ticket->status->label() }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $ticket->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No tickets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
</x-portal-app-layout>
