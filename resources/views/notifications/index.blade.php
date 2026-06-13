<x-app-layout>
    <x-slot name="header">Notifications</x-slot>

    <div class="max-w-3xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden rounded-lg bg-white shadow-sm divide-y divide-gray-100">
            @forelse ($notifications as $notification)
                @php($data = $notification->data)
                <div class="flex items-start justify-between gap-4 px-6 py-4">
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            Task assigned: <a href="{{ $data['url'] }}" class="text-indigo-600 hover:underline">{{ $data['task_title'] }}</a>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            @if ($data['project']) {{ $data['project'] }} · @endif
                            @if ($data['due_date']) Due {{ \Illuminate\Support\Carbon::parse($data['due_date'])->format('d M Y') }} · @endif
                            {{ $notification->created_at->timezone(config('app.timezone'))->diffForHumans() }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" class="shrink-0">
                        @csrf @method('DELETE')
                        <button class="text-xs text-gray-400 hover:text-red-500">Dismiss</button>
                    </form>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">No notifications.</div>
            @endforelse
        </div>

        <div>{{ $notifications->links() }}</div>
    </div>
</x-app-layout>
