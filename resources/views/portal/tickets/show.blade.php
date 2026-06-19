<x-portal-app-layout :header="$ticket->subject">
    <div class="max-w-3xl space-y-5">
        @if (session('status'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        {{-- Ticket meta --}}
        <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100">
            @php
                $statusColor = match($ticket->status->value ?? '') {
                    'open'               => 'bg-blue-100 text-blue-700',
                    'in_progress'        => 'bg-indigo-100 text-indigo-700',
                    'resolved', 'closed' => 'bg-green-100 text-green-700',
                    default              => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                    {{ $ticket->status->label() }}
                </span>
                <span class="text-xs text-gray-400">{{ $ticket->priority->label() }} priority</span>
                <span class="text-xs text-gray-400">· raised {{ $ticket->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</span>
            </div>
            <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $ticket->description }}</p>
        </div>

        {{-- Conversation --}}
        <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Conversation</h2>
            <div class="space-y-4">
                @forelse ($ticket->replies as $reply)
                    @php $isClient = $reply->isFromCustomer(); @endphp
                    <div class="flex {{ $isClient ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-sm {{ $isClient ? 'order-2' : '' }}">
                            <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed
                                        {{ $isClient
                                            ? 'bg-indigo-600 text-white rounded-br-sm'
                                            : 'bg-gray-100 text-gray-800 rounded-bl-sm' }}">
                                {{ $reply->body }}
                            </div>
                            <div class="mt-1 text-xs text-gray-400 {{ $isClient ? 'text-right' : 'text-left' }}">
                                {{ $reply->authorName() }} · {{ $reply->created_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-4">No replies yet. Our team will respond shortly.</p>
                @endforelse
            </div>

            @if ($ticket->status->isOpen())
                <form method="POST" action="{{ route('portal.tickets.reply', $ticket->id) }}"
                      class="mt-5 border-t border-gray-100 pt-4">
                    @csrf
                    <textarea name="body" rows="3"
                              placeholder="Write a reply…"
                              class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400 resize-none"
                              required></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    <div class="mt-3 flex justify-end">
                        <x-primary-button>Send Reply</x-primary-button>
                    </div>
                </form>
            @else
                <p class="mt-4 border-t border-gray-100 pt-4 text-sm text-center text-gray-400">
                    This ticket is {{ $ticket->status->label() }}.
                    <a href="{{ route('portal.tickets.create') }}" class="text-indigo-600 hover:underline">Open a new ticket</a> if you need further help.
                </p>
            @endif
        </div>

        <a href="{{ route('portal.tickets.index') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">← Back to tickets</a>
    </div>
</x-portal-app-layout>
