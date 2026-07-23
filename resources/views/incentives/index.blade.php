<x-app-layout>
    <x-slot name="header">Incentives</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-900">Incentives — {{ $monthLabel }}</h1>
        </div>

        @isset($own)
            {{-- Stat cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">This month's sales</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($own['sales_value']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">Current slab rate</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ rtrim(rtrim(number_format($own['current_slab']['rate'], 1), '0'), '.') }}%</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">Individual incentive</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-600">{{ \App\Support\Money::format($own['individual_incentive']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">Team bonus</p>
                    @if ($own['team_bonus_eligible'])
                        <p class="mt-1 text-2xl font-semibold text-green-600">{{ \App\Support\Money::format($own['team_bonus_share']) }}</p>
                        <p class="text-xs text-green-600">Company target met 🎉</p>
                    @else
                        <p class="mt-1 text-2xl font-semibold text-gray-300">—</p>
                        <p class="text-xs text-gray-400">Company target not yet met this month</p>
                    @endif
                </div>
            </div>

            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="mb-1 text-xs font-medium text-gray-500">Projected total this month</p>
                <p class="text-3xl font-bold text-gray-900">{{ \App\Support\Money::format($own['total_incentive']) }}</p>

                @if ($own['next_slab'])
                    <p class="mt-2 text-xs text-gray-500">
                        {{ \App\Support\Money::format($own['next_slab']['lower'] - $own['sales_value']) }} more in sales this month
                        moves you into the {{ rtrim(rtrim(number_format($own['next_slab']['rate'], 1), '0'), '.') }}% slab.
                    </p>
                @else
                    <p class="mt-2 text-xs text-gray-500">You're in the top slab (20%).</p>
                @endif

                {{-- Slab progress --}}
                <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-gray-100">
                    @php
                        $slabs = [
                            ['upper' => 50_000 * 100, 'rate' => 6.0],
                            ['upper' => 100_000 * 100, 'rate' => 10.0],
                            ['upper' => 150_000 * 100, 'rate' => 12.5],
                            ['upper' => 250_000 * 100, 'rate' => 15.0],
                            ['upper' => null, 'rate' => 20.0],
                        ];
                        $cap = 300_000 * 100;
                    @endphp
                    @foreach ($slabs as $i => $slab)
                        @php
                            $lower = $i === 0 ? 0 : $slabs[$i - 1]['upper'];
                            $width = (($slab['upper'] ?? $cap) - $lower) / $cap * 100;
                            $active = $own['current_slab']['rate'] === $slab['rate'];
                        @endphp
                        <div class="{{ $active ? 'bg-indigo-500' : 'bg-indigo-200' }}" style="width: {{ $width }}%" title="{{ $slab['rate'] }}%"></div>
                    @endforeach
                </div>
            </div>

            {{-- History --}}
            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <p class="p-4 pb-0 text-xs font-medium text-gray-500">Finalized history</p>
                <table class="mt-2 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="px-4 py-2">Month</th>
                            <th class="px-4 py-2">Sales</th>
                            <th class="px-4 py-2">Individual incentive</th>
                            <th class="px-4 py-2">Team bonus</th>
                            <th class="px-4 py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($history as $row)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $row->period_start->format('F Y') }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row->sales_value) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row->individual_incentive) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $row->team_bonus_eligible ? \App\Support\Money::format($row->team_bonus_share) : '—' }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ \App\Support\Money::format($row->total_incentive) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-gray-300" colspan="5">No finalized months yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endisset

        @if ($isManager)
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="mb-3 text-xs font-medium text-gray-500">Company target vs actual — this month</p>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Sales this month</span>
                    <span class="font-medium text-gray-900">
                        {{ \App\Support\Money::format($companySales) }}
                        @if ($companyTarget !== null)
                            / {{ \App\Support\Money::format($companyTarget) }}
                        @endif
                    </span>
                </div>
                <p class="mt-1 text-xs {{ $companyTargetMet ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $companyTargetMet ? 'Target met — team bonus is active this month.' : ($companyTarget === null ? 'No company target set.' : 'Target not yet met — team bonus is not active.') }}
                    <a href="{{ route('sales-dashboard.index') }}" class="text-indigo-600 hover:underline">Edit targets on the Sales Dashboard →</a>
                </p>
            </div>

            <form method="POST" action="{{ route('incentives.settings.update') }}" class="rounded-lg bg-white p-4 shadow-sm">
                @csrf
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <x-input-label for="team_bonus_pool" value="Team bonus pool this month (₹)" />
                        <x-text-input id="team_bonus_pool" name="team_bonus_pool" type="number" step="0.01" min="0"
                                      class="mt-1 block w-48" :value="\App\Support\Money::toRupees($teamBonusPool)" />
                    </div>
                    <x-primary-button type="submit">Save</x-primary-button>
                </div>
                <p class="mt-2 text-xs text-gray-400">Split evenly across active Sales staff the month the company target is met.</p>
            </form>

            <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
                <p class="p-4 pb-0 text-xs font-medium text-gray-500">Rep incentives — this month (live estimate)</p>
                <table class="mt-2 min-w-full divide-y divide-gray-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500">
                            <th class="px-4 py-2">Rep</th>
                            <th class="px-4 py-2">Sales</th>
                            <th class="px-4 py-2">Current slab</th>
                            <th class="px-4 py-2">Individual incentive</th>
                            <th class="px-4 py-2">Team bonus</th>
                            <th class="px-4 py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($repEstimates as $row)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $row['user']->name }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['sales_value']) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ rtrim(rtrim(number_format($row['current_slab']['rate'], 1), '0'), '.') }}%</td>
                                <td class="px-4 py-2 text-gray-700">{{ \App\Support\Money::format($row['individual_incentive']) }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $row['team_bonus_eligible'] ? \App\Support\Money::format($row['team_bonus_share']) : '—' }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ \App\Support\Money::format($row['total_incentive']) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-gray-300" colspan="6">No active Sales reps yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
