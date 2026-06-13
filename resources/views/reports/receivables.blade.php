<x-app-layout>
    <x-slot name="header">Outstanding Receivables</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="text-sm text-gray-500">Total outstanding</div>
            <div class="text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($total) }}</div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3 text-right">Open invoices</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('clients.show', $row['customer']) }}" class="text-indigo-600 hover:underline">{{ $row['customer']->company_name }}</a>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $row['count'] }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">{{ \App\Support\Money::format($row['outstanding']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No outstanding receivables. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
