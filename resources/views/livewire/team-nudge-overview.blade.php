<div class="space-y-6">
    <h2 class="text-base font-semibold text-gray-900">Team completion overview</h2>

    @forelse ($table as $entry)
        @php [$nudge, $rows] = [$entry['nudge'], $entry['rows']]; @endphp
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">{{ $nudge->title }}</h3>
                <span class="text-xs text-gray-500">{{ $entry['done_count'] }}/{{ $entry['total_count'] }} done</span>
            </div>

            @if ($rows->isEmpty())
                <p class="mt-2 text-xs text-gray-400">No targeted, active users for this nudge.</p>
            @else
                <table class="mt-3 min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-1.5">Person</th>
                            <th class="py-1.5">Status</th>
                            <th class="py-1.5">Via</th>
                            <th class="py-1.5">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $row)
                            @php
                                $badge = match ($row['status']) {
                                    \App\Enums\NudgeStatus::Done => 'bg-emerald-50 text-emerald-700',
                                    \App\Enums\NudgeStatus::Snoozed => 'bg-amber-50 text-amber-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr>
                                <td class="py-1.5 text-gray-700">{{ $row['user']->name }}</td>
                                <td class="py-1.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $row['status']->label() }}</span>
                                </td>
                                <td class="py-1.5 text-gray-500">{{ $row['completed_via'] ? ucfirst($row['completed_via']) : '—' }}</td>
                                <td class="py-1.5 text-gray-500">{{ $row['completed_at']?->format('d M, g:i A') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400">No active nudges yet.</p>
    @endforelse
</div>
