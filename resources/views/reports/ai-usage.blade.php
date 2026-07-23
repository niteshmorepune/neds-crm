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

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
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
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Feedback given</p>
                @if ($data['total_feedback_up'] + $data['total_feedback_down'] > 0)
                    <p class="mt-2 text-2xl font-semibold text-gray-900">
                        <span class="text-green-600">{{ $data['total_feedback_up'] }}</span> / <span class="text-red-500">{{ $data['total_feedback_down'] }}</span>
                    </p>
                    <p class="text-xs text-gray-400">helpful / not helpful clicks</p>
                @else
                    <p class="mt-2 text-2xl font-semibold text-gray-300">—</p>
                    <p class="text-xs text-gray-400">no one's rated a draft yet</p>
                @endif
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900">Monthly AI budget</h3>
                @if ($budget['pct'] !== null)
                    <span class="text-sm font-medium {{ $budget['pct'] >= 100 ? 'text-red-600' : ($budget['pct'] >= 80 ? 'text-amber-600' : 'text-gray-600') }}">
                        {{ \App\Support\Money::format($budget['combined_cost_paise']) }} / {{ \App\Support\Money::format($budget['budget_paise']) }} ({{ $budget['pct'] }}%)
                    </span>
                @endif
            </div>
            @if ($budget['pct'] !== null)
                <div class="mt-2 h-2 rounded-full bg-gray-100">
                    <div class="h-2 rounded-full {{ $budget['pct'] >= 100 ? 'bg-red-500' : ($budget['pct'] >= 80 ? 'bg-amber-500' : 'bg-indigo-500') }}"
                         style="width: {{ min(100, $budget['pct']) }}%"></div>
                </div>
                @if ($budget['pct'] >= 100)
                    <p class="mt-1 text-xs text-red-600">Combined CRM + Drishti AI spend has passed this month's budget — consider topping up Anthropic credit.</p>
                @elseif ($budget['pct'] >= 80)
                    <p class="mt-1 text-xs text-amber-600">Approaching this month's AI budget.</p>
                @endif
            @else
                <p class="mt-2 text-xs text-gray-400">No monthly budget set yet — set one below to get a warning before you run low on Anthropic credit.</p>
            @endif

            <form method="POST" action="{{ route('reports.ai-usage.settings.update') }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <x-input-label for="monthly_budget" value="Monthly AI budget (₹)" />
                    <x-text-input id="monthly_budget" name="monthly_budget" type="number" step="0.01" min="0"
                                  class="mt-1 block w-48" :value="\App\Support\Money::toRupees($budget['budget_paise'])" />
                </div>
                <x-primary-button type="submit">Save</x-primary-button>
            </form>
            <p class="mt-2 text-xs text-gray-400">This is a self-tracked spend ceiling, not a real balance check — Anthropic doesn't expose an API for remaining prepaid credit, so this compares estimated spend against a figure you set yourself.</p>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Cross-app usage</h3>
            <p class="mt-1 text-xs text-gray-400">Same AI spend estimate, pulled live from each app that uses Claude.</p>
            <table class="mt-3 min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2">App</th>
                        <th class="py-2 text-right">Calls</th>
                        <th class="py-2 text-right">Tokens</th>
                        <th class="py-2 text-right">Est. cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <td class="py-2 text-gray-700">CRM</td>
                        <td class="py-2 text-right text-gray-600">{{ $data['total_calls'] }}</td>
                        <td class="py-2 text-right text-gray-600">{{ number_format($data['total_input_tokens'] + $data['total_output_tokens']) }}</td>
                        <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($data['estimated_cost_paise']) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-700">Drishti</td>
                        @if ($drishti)
                            <td class="py-2 text-right text-gray-600">{{ $drishti['calls'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ number_format($drishti['input_tokens'] + $drishti['output_tokens']) }}</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($drishti['estimated_cost_paise']) }}</td>
                        @else
                            <td class="py-2 text-right text-gray-300" colspan="3">Unavailable — check Drishti connection</td>
                        @endif
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-700">SMDost</td>
                        @if ($smdost)
                            <td class="py-2 text-right text-gray-600">{{ $smdost['calls'] }}</td>
                            <td class="py-2 text-right text-gray-600">{{ number_format($smdost['input_tokens'] + $smdost['output_tokens']) }}</td>
                            <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($smdost['estimated_cost_paise']) }}</td>
                        @else
                            <td class="py-2 text-right text-gray-300" colspan="3">Unavailable — check SMDost connection</td>
                        @endif
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 font-semibold text-gray-900">
                        <td class="py-2">Combined</td>
                        <td class="py-2 text-right" colspan="2"></td>
                        <td class="py-2 text-right">{{ \App\Support\Money::format($budget['combined_cost_paise']) }}</td>
                    </tr>
                </tfoot>
            </table>
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
                        <th class="py-2 text-right">Feedback</th>
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
                            <td class="py-2 text-right text-gray-600">
                                @if ($r['feedback_up'] + $r['feedback_down'] > 0)
                                    <span class="text-green-600">{{ $r['feedback_up'] }}</span> / <span class="text-red-500">{{ $r['feedback_down'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-400">No AI calls recorded in this period.</td></tr>
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
                            <td class="py-2 text-right">
                                @if ($data['total_feedback_up'] + $data['total_feedback_down'] > 0)
                                    <span class="text-green-600">{{ $data['total_feedback_up'] }}</span> / <span class="text-red-500">{{ $data['total_feedback_down'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="text-xs text-gray-400">Cost is a rough estimate from a configured ₹-per-token rate, not a bill from Anthropic — useful for spotting trends and unused features, not for accounting. Drishti and SMDost's figures are the same kind of estimate, computed the same way on their own side. Feedback is an optional "Helpful / Not helpful" click a person can leave after actually looking at what a feature produced — most calls will have none, that's normal.</p>
    </div>
</x-app-layout>
