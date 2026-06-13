<x-app-layout>
    <x-slot name="header">Calling</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                @if ($isManager)
                    <select name="user_id" class="rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">All users</option>
                        @foreach ($staff as $person)
                            <option value="{{ $person->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $person->id)>{{ $person->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="outcome" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All outcomes</option>
                    @foreach ($outcomes as $outcome)
                        <option value="{{ $outcome->value }}" @selected(($filters['outcome'] ?? '') === $outcome->value)>{{ $outcome->label() }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>
            <div class="flex items-center gap-2">
                <a href="{{ route('calls.index', array_merge($filters, ['pending_followup' => ($filters['pending_followup'] ?? false) ? null : '1'])) }}"
                   class="rounded-md px-3 py-2 text-sm font-medium {{ ($filters['pending_followup'] ?? false) ? 'bg-amber-500 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    Pending follow-ups
                </a>
                <a href="{{ route('calls.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Log a call</a>
            </div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">By</th>
                        <th class="px-4 py-3">Regarding</th>
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3">Outcome</th>
                        <th class="px-4 py-3">Mins</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-4 py-3">Follow-up</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($calls as $call)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-600">{{ $call->called_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $call->user?->name }}</td>
                            <td class="px-4 py-2 text-gray-600">
                                @if ($call->callable instanceof \App\Models\Customer)
                                    <a href="{{ route('clients.show', $call->callable) }}" class="text-indigo-600 hover:underline">{{ $call->callable->company_name }}</a>
                                @elseif ($call->callable instanceof \App\Models\Lead)
                                    <a href="{{ route('leads.show', $call->callable) }}" class="text-indigo-600 hover:underline">{{ $call->callable->name }}</a>
                                @else —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $call->direction->label() }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $call->outcome->label() }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $call->duration_minutes ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 max-w-xs">{{ \Illuminate\Support\Str::limit($call->notes, 50) }}</td>
                            <td class="px-4 py-2">
                                @if ($call->follow_up_at)
                                    <span class="{{ $call->followUpIsDue() ? 'text-red-600 font-medium' : 'text-amber-600' }} text-xs block">
                                        ⏰ {{ $call->follow_up_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                                    </span>
                                    @if ($call->next_action)
                                        <span class="text-xs text-gray-500 block mt-0.5">{{ $call->next_action }}</span>
                                    @endif
                                @elseif ($call->next_action)
                                    <span class="text-xs text-gray-500">{{ $call->next_action }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No calls logged.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $calls->links() }}</div>
    </div>
</x-app-layout>
