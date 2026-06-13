<x-portal-app-layout :header="$ticket->subject">
    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">{{ $ticket->priority->label() }} Â· {{ $ticket->status->label() }} Â· raised {{ $ticket->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</p>
            <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $ticket->description }}</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Conversation</h2>
            <ul class="mt-3 space-y-3">
                @forelse ($ticket->replies as $reply)
                    <li class="rounded-md border-l-2 pl-3 {{ $reply->isFromCustomer() ? 'border-blue-300 bg-blue-50' : 'border-gray-200' }}">
                        <div class="py-2">
                            <div class="text-sm text-gray-800 whitespace-pre-line">{{ $reply->body }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">{{ $reply->authorName() }} Â· {{ $reply->created_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}</div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">No replies yet.</li>
                @endforelse
            </ul>

            @if ($ticket->status->isOpen())
                <form method="POST" action="{{ route('portal.tickets.reply', $ticket->id) }}" class="mt-4 border-t border-gray-100 pt-4">
                    @csrf
                    <textarea name="body" rows="3" placeholder="Write a replyâ€¦" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" required></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    <div class="mt-2 flex justify-end"><x-primary-button>Send reply</x-primary-button></div>
                </form>
            @else
                <p class="mt-4 border-t border-gray-100 pt-4 text-sm text-gray-400">This ticket is {{ $ticket->status->label() }}.</p>
            @endif
        </div>

        <a href="{{ route('portal.tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">â† Back to tickets</a>
    </div>
</x-portal-app-layout>

