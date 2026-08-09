<x-app-layout>
    <x-slot name="header">Action Center</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <p class="text-sm text-gray-500">Everything across the CRM that needs a manager's attention right now, in one place.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($signals as $signal)
                <a href="{{ $signal['route'] }}" class="flex items-center gap-4 rounded-lg bg-white p-5 shadow-sm transition hover:shadow-md">
                    <span @class([
                        'flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-lg',
                        'bg-red-50' => $signal['color'] === 'red',
                        'bg-orange-50' => $signal['color'] === 'orange',
                        'bg-amber-50' => $signal['color'] === 'amber',
                    ])>{{ $signal['icon'] }}</span>
                    <div class="min-w-0 flex-1">
                        <p @class([
                            'text-2xl font-semibold tabular-nums',
                            'text-gray-300' => $signal['count'] === 0,
                            'text-gray-900' => $signal['count'] > 0,
                        ])>{{ $signal['count'] }}</p>
                        <p class="text-sm text-gray-600">{{ $signal['label'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- No team-wide follow-up list page exists yet (My Follow-Up
             Reminders is per-user, dashboard-only), so shown inline here
             rather than as a drill-down link. --}}
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">Pending follow-ups</h2>
                <p class="text-xs text-gray-500">{{ $followUpCount }} pending</p>
            </div>
            <ul class="mt-3 divide-y divide-gray-100 text-sm">
                @forelse ($followUps as $reminder)
                    <li class="flex items-center justify-between py-2">
                        <div class="min-w-0">
                            @if ($reminder->customer)
                                <a href="{{ route('clients.show', $reminder->customer) }}" class="font-medium text-indigo-600 hover:underline">{{ $reminder->customer->company_name }}</a>
                            @else
                                <span class="font-medium text-gray-400">Client removed</span>
                            @endif
                            <span class="text-gray-500">— {{ $reminder->next_action }}</span>
                        </div>
                        <span class="shrink-0 text-xs text-gray-500">{{ $reminder->user?->name }} · {{ $reminder->remind_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No pending follow-ups.</li>
                @endforelse
            </ul>
        </div>

        <p class="text-xs text-gray-400">Not tracked here yet: stagnant deals (no reusable "hasn't moved" signal exists in the app today — flagged for a future build) and project health (Tier 2).</p>
    </div>
</x-app-layout>
