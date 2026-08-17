<x-app-layout>
    <x-slot name="header">Team Targets</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            One KRA target per role, this calendar month. Sales targets live on the
            <a href="{{ route('sales-dashboard.index') }}" class="text-indigo-600 hover:underline">Sales Dashboard</a> instead.
        </p>

        <form method="POST" action="{{ route('role-targets.store') }}" class="space-y-6">
            @csrf

            @foreach ($sections as $section)
                <div class="overflow-hidden overflow-x-auto rounded-lg bg-white p-4 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $section['role']->label() }} — {{ $section['metric']->label() }}</h2>
                        <div class="flex items-end gap-2">
                            <div>
                                <x-input-label for="role_wide_{{ $section['role']->value }}" value="Role-wide target this month" class="text-xs" />
                                <x-text-input
                                    id="role_wide_{{ $section['role']->value }}"
                                    name="role_wide_targets[{{ $section['role']->value }}]"
                                    type="number" step="{{ $section['metric']->isMoney() ? '0.01' : '1' }}" min="0"
                                    class="mt-1 block w-40"
                                    :value="$section['roleWide']['target'] !== null ? ($section['metric']->isMoney() ? \App\Support\Money::toRupees($section['roleWide']['target']) : $section['roleWide']['target']) : null" />
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 max-w-sm">
                        <x-target-progress-bar :metric="$section['metric']" :target="$section['roleWide']['target']" :actual="$section['roleWide']['actual']" :pct="$section['roleWide']['pct']" />
                    </div>

                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Name</th>
                                <th class="px-4 py-2">This month so far</th>
                                <th class="px-4 py-2">Target</th>
                                <th class="px-4 py-2">% to target</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($section['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-2 text-gray-900">
                                        <a href="{{ route('employees.show', $row['user']) }}" class="hover:text-indigo-600 hover:underline">{{ $row['user']->name }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        {{ $section['metric']->isMoney() ? \App\Support\Money::format($row['actual']) : number_format($row['actual']) }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <x-text-input type="number" step="{{ $section['metric']->isMoney() ? '0.01' : '1' }}" min="0" class="block w-32"
                                                      name="rep_targets[{{ $row['user']->id }}]"
                                                      :value="$row['target'] !== null ? ($section['metric']->isMoney() ? \App\Support\Money::toRupees($row['target']) : $row['target']) : null" />
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">{{ $row['pct'] !== null ? $row['pct'].'%' : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No active {{ strtolower($section['role']->label()) }} users yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach

            <x-primary-button type="submit">Save targets</x-primary-button>
        </form>
    </div>
</x-app-layout>
