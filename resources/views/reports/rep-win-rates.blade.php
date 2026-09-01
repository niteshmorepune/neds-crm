<x-app-layout>
    <x-slot name="header">Rep Win Rates</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.rep-win-rates.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <p class="text-xs text-gray-400">
            Measurement only — recorded automatically on the 1st of each month for the month that just
            ended. Not used anywhere in lead routing or assignment today; this exists so the trend is
            there to look at once there's enough of it.
        </p>

        @if (count($rows) > 0)
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $from->format('F Y') }}</h3>
                <div class="mt-3 space-y-6">
                    @foreach ($rows as $row)
                        <div class="border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                            <div class="flex items-baseline justify-between">
                                <p class="text-sm font-medium text-gray-800">{{ $row['user'] }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ $row['overall']['won_count'] }}W / {{ $row['overall']['lost_count'] }}L
                                    <span class="font-medium text-gray-900">
                                        — {{ $row['overall']['win_rate'] !== null ? $row['overall']['win_rate'].'%' : 'N/A' }}
                                    </span>
                                </p>
                            </div>

                            @if (count($row['by_source']) > 0)
                                <div class="mt-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">By lead source</p>
                                    <table class="mt-1 min-w-full text-sm">
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($row['by_source'] as $s)
                                                <tr>
                                                    <td class="py-1 text-gray-600">{{ $s['label'] }}</td>
                                                    <td class="py-1 text-right text-gray-500">{{ $s['won_count'] }}W / {{ $s['lost_count'] }}L</td>
                                                    <td class="py-1 text-right font-medium text-gray-800">{{ $s['win_rate'] !== null ? $s['win_rate'].'%' : 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            @if (count($row['by_score_band']) > 0)
                                <div class="mt-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">By score band</p>
                                    <table class="mt-1 min-w-full text-sm">
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($row['by_score_band'] as $b)
                                                <tr>
                                                    <td class="py-1 text-gray-600">{{ $b['label'] }}</td>
                                                    <td class="py-1 text-right text-gray-500">{{ $b['won_count'] }}W / {{ $b['lost_count'] }}L</td>
                                                    <td class="py-1 text-right font-medium text-gray-800">{{ $b['win_rate'] !== null ? $b['win_rate'].'%' : 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-lg bg-white p-6 text-center text-gray-400 shadow-sm">
                No snapshot recorded for {{ $from->format('F Y') }} yet. Snapshots run on the 1st of each
                month for the month that just ended — pick an earlier month, or check back after the 1st.
            </div>
        @endif
    </div>
</x-app-layout>
