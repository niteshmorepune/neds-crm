<x-app-layout>
    <x-slot name="header">Employee 360°</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        <p class="text-sm text-gray-500">Performance, workload, tickets, attendance, and manager notes — one page per employee.</p>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $employee->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $employee->allRoles()->map->label()->join(' + ') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('employees.show', $employee) }}" class="text-indigo-600 hover:underline">View 360° →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No active employees.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
