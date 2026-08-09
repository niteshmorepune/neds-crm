<x-app-layout>
    <x-slot name="header">Recurring Invoices</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</a>
            <div class="flex flex-wrap items-center gap-4">
                <form method="GET" class="flex items-center gap-2">
                    @if ($showEnded)
                        <input type="hidden" name="show_ended" value="1" />
                    @endif
                    <label for="month" class="text-sm text-gray-500">Billing month</label>
                    <input type="month" id="month" name="month" value="{{ $month }}"
                           class="rounded-md border-gray-300 text-sm shadow-sm" />
                    <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
                    @if ($month)
                        <a href="{{ route('recurring-invoices.index', ['show_ended' => $showEnded ? 1 : null]) }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
                    @endif
                </form>
                <a href="{{ route('recurring-invoices.index', ['show_ended' => $showEnded ? null : 1, 'month' => $month ?: null]) }}"
                   class="text-sm text-gray-500 hover:text-gray-700">
                    {{ $showEnded ? 'Hide ended templates' : 'Show ended templates' }}
                </a>
                <a href="{{ route('recurring-invoices.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">New Recurring</a>
            </div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Frequency</th>
                        <th class="px-4 py-3">Next run</th>
                        <th class="px-4 py-3">Active</th>
                        @if ($month)
                            <th class="px-4 py-3">Invoice for {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}</th>
                        @endif
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recurring as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-700">{{ $r->customer?->company_name ?? 'Client removed' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $r->frequency->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $r->next_run_on->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $r->is_active,
                                    'bg-gray-100 text-gray-600' => ! $r->is_active,
                                ])>{{ $r->is_active ? 'Active' : ($r->hasEnded() ? 'Ended' : 'Paused') }}</span>
                            </td>
                            @if ($month)
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($invoice = $monthlyInvoices->get($r->id))
                                        <a href="{{ route('invoices.show', $invoice) }}" class="text-indigo-600 hover:underline">{{ $invoice->invoice_number }}</a>
                                        <span class="text-xs text-gray-400">· {{ $invoice->status->label() }} · {{ \App\Support\Money::format($invoice->total) }}</span>
                                    @else
                                        <span class="text-gray-300">Not billed this month</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('recurring-invoices.show', $r) }}" class="text-indigo-600 hover:text-indigo-500">Invoices</a>
                                <a href="{{ route('recurring-invoices.edit', $r) }}" class="ml-3 text-gray-500 hover:text-gray-700">Edit</a>
                                <form method="POST" action="{{ route('recurring-invoices.toggle', $r) }}" class="inline">
                                    @csrf @method('PUT')
                                    <button class="ml-3 text-gray-500 hover:text-gray-700">{{ $r->is_active ? 'Pause' : 'Activate' }}</button>
                                </form>
                                <form method="POST" action="{{ route('recurring-invoices.destroy', $r) }}" class="inline"
                                      onsubmit="return confirm('Delete this recurring invoice? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button class="ml-3 text-red-600 hover:text-red-500">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $month ? 6 : 5 }}" class="px-4 py-10 text-center text-gray-400">No recurring invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $recurring->links() }}</div>
    </div>
</x-app-layout>
