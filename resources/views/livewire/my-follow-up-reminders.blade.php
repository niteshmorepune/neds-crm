<div>
    <div class="border-t border-gray-100 px-4 py-3 sm:px-5">
        <button type="button" wire:click="toggleForm" class="flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500">
            <span>{{ $showForm ? '▼' : '▶' }}</span>
            <span>{{ $showForm ? 'Follow-up reminder' : '+ Add follow-up reminder' }}</span>
        </button>

        @if ($showForm)
            <form wire:submit="save" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <x-input-label for="reminder_customer_id" value="Client" />
                    <select id="reminder_customer_id" wire:model="customer_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">—</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->company_name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <x-input-label for="reminder_remind_at" value="Remind me on" />
                    <input id="reminder_remind_at" type="datetime-local" wire:model="remind_at"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    @error('remind_at') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <x-input-label for="reminder_next_action" value="Next action" />
                    <input id="reminder_next_action" type="text" wire:model="next_action" placeholder="e.g. Send quotation, Call back at 3 PM"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    @error('next_action') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="sm:col-span-3">
                    <x-primary-button type="submit">Save reminder</x-primary-button>
                </div>
            </form>
        @endif
    </div>

    @foreach ($reminders as $reminder)
        <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md {{ $reminder->isDue() ? 'bg-red-50' : 'bg-amber-50' }} text-sm">🔔</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">
                    {{ $reminder->next_action }}
                    @if ($reminder->customer)
                        — <a href="{{ route('clients.show', $reminder->customer_id) }}" class="text-indigo-600 hover:underline">{{ $reminder->customer->company_name }}</a>
                    @elseif ($reminder->customer_id)
                        — <span class="text-gray-400">Client removed</span>
                    @endif
                </p>
                <p class="mt-0.5 text-xs {{ $reminder->isDue() ? 'text-red-600' : 'text-gray-500' }}">
                    {{ $reminder->isDue() ? 'Overdue —' : 'Due' }} {{ $reminder->remind_at->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}
                </p>
            </div>
            <button type="button" wire:click="markDone({{ $reminder->id }})" class="shrink-0 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-emerald-500">Done</button>
        </div>
    @endforeach
</div>
