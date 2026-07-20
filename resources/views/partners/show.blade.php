<x-app-layout>
    <x-slot name="header">{{ $partner->name }}</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $partner->name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $partner->email ?? '—' }} · {{ $partner->phone ?? '—' }}
                    </p>
                    @if ($partner->notes)
                        <p class="mt-2 text-sm text-gray-600">{{ $partner->notes }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('clients.index', ['referring_partner_id' => $partner->id, 'status' => 'all']) }}"
                       class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ $partner->referredCustomers->count() }} referred client(s)
                    </a>
                    @can('update', $partner)
                        <a href="{{ route('partners.edit', $partner) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-900">Billed — last 6 months</h3>
                <p class="text-sm text-gray-500">Total: <span class="font-semibold text-gray-900">{{ \App\Support\Money::format($billed->sum('amount')) }}</span></p>
            </div>
            <p class="mt-1 text-sm text-gray-500">Every referred client, including ones billed nothing in the window — invoiced (issued) amounts, not just what's been paid.</p>

            <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($billedByMonth as $m)
                    <div class="rounded-md border border-gray-200 p-3 text-center">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $m['label'] }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ \App\Support\Money::format($m['amount']) }}</p>
                        <p class="text-xs text-gray-400">{{ $m['invoice_count'] }} invoice(s)</p>
                    </div>
                @endforeach
            </div>

            <h4 class="mt-5 text-sm font-medium text-gray-700">By client</h4>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2">Client</th>
                            <th class="py-2 text-right">Invoices</th>
                            <th class="py-2 text-right">Billed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($billed as $b)
                            <tr>
                                <td class="py-2 text-gray-900">
                                    <a href="{{ route('clients.show', $b['customer']) }}" class="font-medium text-indigo-600 hover:underline">
                                        {{ $b['customer']->company_name }}
                                    </a>
                                </td>
                                <td class="py-2 text-right text-gray-600">{{ $b['invoice_count'] }}</td>
                                <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($b['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-400">No referred clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Client health</h3>
            <p class="mt-1 text-sm text-gray-500">Which of this partner's clients need a collections follow-up or are ready for the next milestone invoice.</p>
            <div class="mt-3">
                @include('collections._client-table', ['rows' => $rows, 'showPartnerColumn' => false])
            </div>
        </div>
    </div>
</x-app-layout>
