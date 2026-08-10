<x-portal-app-layout header="Notifications">

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-100 divide-y divide-gray-100">
        @forelse ($notifications as $notification)
            @php($data = $notification->data)
            @php($typeIcon = match ($data['type'] ?? null) {
                'quotation_awaiting_decision' => '📄',
                'invoice_issued'              => '🧾',
                'ticket_reply'                => '💬',
                'project_update'              => '📁',
                default                       => '🔔',
            })
            <div class="flex items-start justify-between gap-4 px-5 py-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">
                        {{ $typeIcon }}
                        @if (! empty($data['url']))
                            <a href="{{ $data['url'] }}" class="text-indigo-600 hover:underline">{{ $data['message'] }}</a>
                        @else
                            {{ $data['message'] }}
                        @endif
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $notification->created_at->timezone(config('app.display_timezone'))->diffForHumans() }}</p>
                </div>
                <form method="POST" action="{{ route('portal.notifications.destroy', $notification->id) }}" class="shrink-0">
                    @csrf @method('DELETE')
                    <button class="text-xs text-gray-400 hover:text-red-500">Dismiss</button>
                </form>
            </div>
        @empty
            <div class="px-6 py-10 text-center text-sm text-gray-400">No notifications.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $notifications->links() }}</div>
</x-portal-app-layout>
