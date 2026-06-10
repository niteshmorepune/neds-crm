<x-app-layout>
    <x-slot name="header">Invoices</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <form method="GET" class="flex items-center gap-2">
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
            </form>
            <a href="{{ route('recurring-invoices.index') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Recurring invoices</a>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Invoice #</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->invoice_number }}</a></td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->customer->company_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->status->label() }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ \App\Support\Money::format($invoice->total) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-gray-500 hover:text-gray-700">View</a>
                                <a href="{{ route('invoices.pdf', $invoice) }}" class="ml-3 text-gray-500 hover:text-gray-700" target="_blank">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $invoices->links() }}</div>
    </div>
</x-app-layout>
