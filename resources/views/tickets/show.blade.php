<x-app-layout>
    <x-slot name="header">Ticket #{{ $ticket->id }}</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">{{ $ticket->subject }}</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                @if ($ticket->customer)
                                    <a href="{{ route('clients.show', $ticket->customer) }}" class="text-indigo-600 hover:underline">{{ $ticket->customer->company_name }}</a>
                                @else
                                    Client removed
                                @endif
                                · {{ $ticket->priority->label() }} · {{ $ticket->status->label() }}
                            </p>
                        </div>
                        <a href="{{ route('tickets.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
                    </div>

                    @if ($ticket->isSlaBreached())
                        <div class="mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
                            SLA breached — was due {{ $ticket->sla_due_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}.
                        </div>
                    @elseif ($ticket->sla_due_at)
                        <div class="mt-3 text-sm text-gray-500">SLA due {{ $ticket->sla_due_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}.</div>
                    @endif

                    @if (isset($drishtiUrl))
                        <div class="mt-3 flex items-center gap-2 rounded-md bg-blue-50 border border-blue-200 px-3 py-2 text-sm text-blue-800">
                            <svg class="h-4 w-4 shrink-0 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            <span>Drishti context:</span>
                            <a href="{{ $drishtiUrl }}" target="_blank" rel="noopener noreferrer"
                               class="font-medium underline underline-offset-2 hover:text-blue-600">
                                Open in Drishti &rarr;
                            </a>
                        </div>
                    @endif

                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $ticket->description }}</p>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">Conversation</h2>
                    <livewire:ticket-replies :ticket="$ticket" :can-manage="$canManage" />
                </div>

                <div id="attachments" class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Attachments</h2>
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        @forelse ($ticket->attachments as $attachment)
                            <li class="flex items-center justify-between py-2">
                                <a href="{{ route('attachments.download', $attachment) }}" class="text-indigo-600 hover:underline">{{ $attachment->original_name }}</a>
                                <span class="text-xs text-gray-400">{{ $attachment->humanSize() }}</span>
                            </li>
                        @empty
                            <li class="py-2 text-gray-400">No attachments.</li>
                        @endforelse
                    </ul>
                    @can('update', $ticket)
                        @if (session('attachment_uploaded'))
                            <p class="mt-3 text-sm font-medium text-green-700">&#10003; &ldquo;{{ session('attachment_uploaded') }}&rdquo; uploaded successfully.</p>
                        @endif
                        <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data"
                              class="mt-4" x-data="{ fileName: '' }">
                            @csrf
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="file" name="file" required class="text-sm"
                                       x-on:change="fileName = $event.target.files[0]?.name ?? ''" />
                                <x-primary-button>Upload</x-primary-button>
                            </div>
                            <p x-show="fileName" x-text="'\u{1F4CE} ' + fileName + ' — ready to upload'" class="mt-1 text-xs text-gray-500" style="display:none"></p>
                            @error('file') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </form>
                    @endcan
                </div>
            </div>

            {{-- Manage panel --}}
            @can('update', $ticket)
                <div class="rounded-lg bg-white p-6 shadow-sm h-fit">
                    <h2 class="text-base font-semibold text-gray-900">Manage</h2>
                    <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-4 space-y-3">
                        @csrf @method('PATCH')
                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($ticket->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="priority" value="Priority" />
                            <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}" @selected($ticket->priority === $priority)>{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="assignee_id" value="Assignee" />
                            <select id="assignee_id" name="assignee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach ($staff as $person)
                                    <option value="{{ $person->id }}" @selected($ticket->assignee_id === $person->id)>{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Update</x-primary-button>
                    </form>

                    @if ($ticket->status !== \App\Enums\TicketStatus::Resolved)
                        <form method="POST" action="{{ route('tickets.resolve', $ticket) }}" class="mt-3">
                            @csrf
                            <button class="w-full rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-500">Mark resolved</button>
                        </form>
                    @endif
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
