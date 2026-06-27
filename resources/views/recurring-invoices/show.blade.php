<x-app-layout>
    <x-slot name="header">Recurring Invoice — {{ $recurring->customer?->company_name }}</x-slot>

    @php
        $alreadyGeneratedToday = $recurring->invoices()->whereDate('issue_date', today())->exists();
        $canGenerate = $recurring->is_active && ! $alreadyGeneratedToday;
    @endphp

    <div class="max-w-7xl mx-auto space-y-5">

        {{-- Back + Actions --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('recurring-invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Recurring Invoices</a>
            <div class="flex gap-2">
                @can('create', \App\Models\Invoice::class)
                    @if ($canGenerate)
                        <form method="POST" action="{{ route('recurring-invoices.generate-now', $recurring) }}"
                              onsubmit="return confirm('Generate and email an invoice to {{ addslashes($recurring->customer?->company_name) }} now?')">
                            @csrf
                            <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                                Generate &amp; Send Now
                            </button>
                        </form>
                    @elseif ($alreadyGeneratedToday)
                        <span class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-400">Already generated today</span>
                    @endif
                @endcan
                <a href="{{ route('recurring-invoices.edit', $recurring) }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit Schedule</a>
                <form method="POST" action="{{ route('recurring-invoices.toggle', $recurring) }}">
                    @csrf @method('PUT')
                    <button class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ $recurring->is_active ? 'Pause' : 'Activate' }}
                    </button>
                </form>
                @can('create', \App\Models\Invoice::class)
                    <form method="POST" action="{{ route('recurring-invoices.destroy', $recurring) }}"
                          onsubmit="return confirm('Delete this recurring invoice template? Generated invoices will not be deleted.')">
                        @csrf @method('DELETE')
                        <button class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                    </form>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Summary card --}}
        <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-100 p-5">
            <dl class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-4 text-sm">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Client</dt>
                    <dd class="mt-1 text-gray-900 font-medium">{{ $recurring->customer?->company_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Frequency</dt>
                    <dd class="mt-1 text-gray-900">{{ $recurring->frequency->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Next billing</dt>
                    <dd class="mt-1 text-gray-900">{{ $recurring->next_run_on->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Status</dt>
                    <dd class="mt-1">
                        <span @class([
                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-green-100 text-green-800' => $recurring->is_active,
                            'bg-gray-100 text-gray-600' => ! $recurring->is_active,
                        ])>{{ $recurring->is_active ? 'Active' : 'Paused' }}</span>
                    </dd>
                </div>
                @if($recurring->service)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Service</dt>
                    <dd class="mt-1 text-gray-900">{{ $recurring->service->name }}</dd>
                </div>
                @endif
                @if($recurring->end_date)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">End date</dt>
                    <dd class="mt-1 text-gray-900">{{ $recurring->end_date->format('d M Y') }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Generated invoices --}}
        <div>
            <h2 class="text-sm font-semibold text-gray-700 mb-2">Generated Invoices</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Invoice #</th>
                            <th class="px-4 py-3">Issued</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($invoices as $invoice)
                            @php
                                $statusColor = match($invoice->status->value) {
                                    'paid'           => 'bg-green-100 text-green-800',
                                    'overdue'        => 'bg-red-100 text-red-800',
                                    'partially_paid' => 'bg-amber-100 text-amber-800',
                                    default          => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ $invoice->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $invoice->issue_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColor }}">{{ $invoice->status->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ \App\Support\Money::format($invoice->total) }}</td>
                                <td class="px-4 py-3 text-right {{ $invoice->balance() > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                                <td class="px-4 py-3 text-right space-x-3">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-gray-500 hover:text-gray-700 text-xs">View / Pay</a>
                                    @can('update', $invoice)
                                    <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="inline">
                                        @csrf
                                        <button class="text-indigo-600 hover:text-indigo-500 text-xs">Send Email</button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                    No invoices generated yet. The next one will be created on {{ $recurring->next_run_on->format('d M Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $invoices->links() }}</div>
        </div>

    </div>
</x-app-layout>
