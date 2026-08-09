<x-app-layout>
    <x-slot name="header">Team Workload &amp; Capacity</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <p class="text-sm text-gray-500">Open task load per person against their own role's average. Overloaded = more than 1.5&times; the role average, or 3+ overdue tasks.</p>

        @foreach ($rows->groupBy('role') as $role => $roleRows)
            <div class="rounded-lg bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ $roleRows->first()['user']->role->label() }}</h2>
                    <p class="text-xs text-gray-500">Role average: {{ $roleRows->first()['role_average_open_tasks'] }} open tasks</p>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-2.5">Name</th>
                            <th class="px-5 py-2.5">Open tasks</th>
                            <th class="px-5 py-2.5">Overdue</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($roleRows as $row)
                            <tr>
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    <a href="{{ route('tasks.index', ['assignee' => $row['user']->id, 'type' => 'all']) }}" class="hover:underline">{{ $row['user']->name }}</a>
                                </td>
                                <td class="px-5 py-3 tabular-nums text-gray-600">{{ $row['open_tasks'] }}</td>
                                <td @class(['px-5 py-3 tabular-nums', 'text-red-600 font-medium' => $row['overdue_tasks'] > 0, 'text-gray-600' => $row['overdue_tasks'] === 0])>{{ $row['overdue_tasks'] }}</td>
                                <td class="px-5 py-3">
                                    @if ($row['overloaded'])
                                        <span class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">Overloaded</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        @if ($rows->isEmpty())
            <div class="rounded-lg bg-white p-8 text-center text-gray-400 shadow-sm">No active staff in a workload-tracked role yet.</div>
        @endif
    </div>
</x-app-layout>
