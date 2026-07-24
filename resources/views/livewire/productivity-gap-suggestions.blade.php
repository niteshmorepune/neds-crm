<div class="space-y-3">
    @if ($aiEnabled)
        <div>
            <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                    class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">✨ Suggest Improvements for the Team</span>
                <span wire:loading wire:target="generate">Thinking…</span>
            </button>
            @if ($error)
                <p class="mt-1 text-xs text-red-600">{{ $error }}</p>
            @endif
        </div>
    @endif

    <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Employee</th>
                    <th class="px-4 py-3 text-right">Tasks done</th>
                    <th class="px-4 py-3 text-right">On-time %</th>
                    <th class="px-4 py-3 text-right">Calls</th>
                    <th class="px-4 py-3 text-right">Leads conv.</th>
                    <th class="px-4 py-3 text-right">Attendance %</th>
                    <th class="px-4 py-3 text-right">Daily reports</th>
                    <th class="px-4 py-3 text-right">Score</th>
                    <th class="px-4 py-3 text-right">Rank</th>
                    <th class="px-4 py-3">Focus area</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($grouped as $role => $roleRows)
                    <tr class="bg-gray-50">
                        <td colspan="10" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $role }}</td>
                    </tr>
                    @foreach ($roleRows as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $r['user'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['tasks_completed'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['on_time_pct'] !== null ? $r['on_time_pct'].'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['calls_made'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['leads_converted'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['attendance_pct'] !== null ? $r['attendance_pct'].'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['daily_reports'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $r['score'] !== null ? $r['score'] : '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                @if ($r['rank'] !== null)
                                    #{{ $r['rank'] }} of {{ $r['role_group_size'] }}
                                @else
                                    <span class="text-xs text-gray-400">{{ $r['ranking_note'] ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if (! empty($suggestions[$r['user_id']]))
                                    {{ $suggestions[$r['user_id']] }}
                                @elseif ($r['weakest_metric'])
                                    <span class="text-xs text-gray-400">{{ str_replace('_', ' ', $r['weakest_metric']) }} ({{ $r['weakest_percentile'] }}th percentile)</span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-gray-400">No users.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
