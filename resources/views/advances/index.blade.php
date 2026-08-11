<x-app-layout>
    <x-slot name="header">Client Advances</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="text-sm text-gray-500">Total unapplied</div>
            <div class="text-2xl font-semibold text-blue-700">{{ \App\Support\Money::format($advances->sum(fn ($a) => $a->remaining())) }}</div>
            <p class="mt-1 text-xs text-gray-400">Money received but not yet applied to an invoice.</p>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Received</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Mode</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($advances as $advance)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $advance->received_on->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                @if ($advance->customer)
                                    <a href="{{ route('clients.show', $advance->customer) }}#invoices" class="text-indigo-600 hover:underline">{{ $advance->customer->company_name }}</a>
                                @else
                                    <span class="text-gray-400">Client removed</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $advance->mode->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $advance->reference ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $advance->status->badgeClass() }}">{{ $advance->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ \App\Support\Money::format($advance->amount) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-blue-700">{{ \App\Support\Money::format($advance->remaining()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No unapplied advances right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $advances->links() }}</div>
    </div>
</x-app-layout>
