<x-app-layout>
    <x-slot name="header">Revenue Report</x-slot>

    @php $fyStart = (int) $from->year; @endphp

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <select name="fy" class="rounded-md border-gray-300 text-sm shadow-sm">
                    @for ($y = now()->year; $y >= now()->year - 4; $y--)
                        <option value="{{ $y }}" @selected($y === $fyStart)>FY {{ $y }}–{{ ($y + 1) % 100 }}</option>
                    @endfor
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.revenue.export', ['fy' => $fyStart]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Total invoiced</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($data['total']) }}</p>
                <p class="text-xs text-gray-400">{{ $data['invoice_count'] }} invoices · {{ $from->format('M Y') }}–{{ $to->format('M Y') }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Recurring</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ \App\Support\Money::format($data['recurring']) }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">One-time</p>
                <p class="mt-2 text-2xl font-semibold text-green-600">{{ \App\Support\Money::format($data['one_time']) }}</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">By month</h3>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="py-2">Month</th><th class="py-2 text-right">Recurring</th><th class="py-2 text-right">One-time</th><th class="py-2 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['monthly'] as $m)
                        <tr>
                            <td class="py-2 text-gray-700">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m['month'])->format('M Y') }}</td>
                            <td class="py-2 text-right text-gray-600">{{ \App\Support\Money::format($m['recurring']) }}</td>
                            <td class="py-2 text-right text-gray-600">{{ \App\Support\Money::format($m['one_time']) }}</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($m['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">No invoices in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">By service</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($data['by_service'] as $s)
                        <li class="flex justify-between"><span class="text-gray-700">{{ $s['name'] }}</span><span class="text-gray-900">{{ \App\Support\Money::format($s['total']) }}</span></li>
                    @empty
                        <li class="text-gray-400">No data.</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">By client</h3>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($data['by_client'] as $c)
                        <li class="flex justify-between"><span class="text-gray-700">{{ $c['name'] }}</span><span class="text-gray-900">{{ \App\Support\Money::format($c['total']) }}</span></li>
                    @empty
                        <li class="text-gray-400">No data.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
