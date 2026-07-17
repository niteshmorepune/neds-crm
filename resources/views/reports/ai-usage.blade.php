<x-app-layout>
    <x-slot name="header">AI Usage Report</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.ai-usage.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <p class="text-sm text-gray-500">{{ $from->format('M Y') }} — which AI features the team is actually using, and a rough sense of what it costs.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">AI calls this month</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $data['total_calls'] }}</p>
                <p class="text-xs text-gray-400">across {{ count($data['by_feature']) }} feature{{ count($data['by_feature']) === 1 ? '' : 's' }}</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Estimated cost</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-600">{{ \App\Support\Money::format($data['estimated_cost_paise']) }}</p>
                <p class="text-xs text-gray-400">based on configured per-model rates — see note below</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Tokens processed</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($data['total_input_tokens'] + $data['total_output_tokens']) }}</p>
                <p class="text-xs text-gray-400">{{ number_format($data['total_input_tokens']) }} in / {{ number_format($data['total_output_tokens']) }} out</p>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">By feature</h3>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">Feature</th>
                        <th class="py-2 text-right">Calls</th>
                        <th class="py-2 text-right">Input tokens</th>
                        <th class="py-2 text-right">Output tokens</th>
                        <th class="py-2 text-right">Est. cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['by_feature'] as $r)
                        <tr>
                            <td class="py-2 text-gray-700">{{ $r['label'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ $r['calls'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ number_format($r['input_tokens']) }}</td>
                            <td class="py-2 text-right text-gray-600">{{ number_format($r['output_tokens']) }}</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($r['estimated_cost_paise']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-400">No AI calls recorded in this period.</td></tr>
                    @endforelse
                </tbody>
                @if (! empty($data['by_feature']))
                    <tfoot>
                        <tr class="border-t border-gray-200 font-semibold text-gray-900">
                            <td class="py-2">Total</td>
                            <td class="py-2 text-right">{{ $data['total_calls'] }}</td>
                            <td class="py-2 text-right">{{ number_format($data['total_input_tokens']) }}</td>
                            <td class="py-2 text-right">{{ number_format($data['total_output_tokens']) }}</td>
                            <td class="py-2 text-right">{{ \App\Support\Money::format($data['estimated_cost_paise']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="text-xs text-gray-400">Cost is a rough estimate from a configured ₹-per-token rate, not a bill from Anthropic — useful for spotting trends and unused features, not for accounting.</p>
    </div>
</x-app-layout>
