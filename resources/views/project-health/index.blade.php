<x-app-layout>
    <x-slot name="header">Project Health</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <p class="text-sm text-gray-500">Every active or on-hold project, scored red/orange/yellow/green by deadline proximity, task completion, and overdue tasks.</p>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3"></th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Deadline</th>
                        <th class="px-4 py-3">Completion</th>
                        <th class="px-4 py-3">Overdue tasks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        @php($project = $row['project'])
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-lg">
                                @switch($row['status'])
                                    @case('red') 🔴 @break
                                    @case('orange') 🟠 @break
                                    @case('yellow') 🟡 @break
                                    @default 🟢
                                @endswitch
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('projects.show', $project) }}" class="hover:underline">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->customer->company_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->end_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-gray-600">{{ $row['completion'] !== null ? $row['completion'].'%' : 'No tasks yet' }}</td>
                            <td @class(['px-4 py-3 tabular-nums', 'text-red-600 font-medium' => $row['overdue_tasks'] > 0, 'text-gray-600' => $row['overdue_tasks'] === 0])>{{ $row['overdue_tasks'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No active or on-hold projects.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
