<x-portal-app-layout header="Your Quotations">

    {{-- Mobile cards --}}
    <div class="sm:hidden space-y-3">
        @forelse ($quotations as $quotation)
            @php
                $statusColor = match($quotation->status->value) {
                    'accepted'  => 'bg-green-100 text-green-700',
                    'rejected'  => 'bg-red-100 text-red-700',
                    'sent'      => 'bg-indigo-100 text-indigo-700',
                    default     => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <div class="rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-900 text-sm">{{ $quotation->number ?? '—' }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $quotation->created_at->format('d M Y') }}</p>
                    </div>
                    <span class="shrink-0 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                        {{ $quotation->status->label() }}
                    </span>
                </div>
                @if ($quotation->items->isNotEmpty())
                    <p class="mt-2 text-xs text-gray-500">{{ $quotation->items->first()->description }}{{ $quotation->items->count() > 1 ? ' +'.($quotation->items->count()-1).' more' : '' }}</p>
                @endif
                <div class="mt-3 flex items-center justify-between text-sm">
                    <div>
                        <span class="text-gray-400 text-xs">Total</span>
                        <p class="font-semibold text-gray-900">{{ \App\Support\Money::format($quotation->total) }}</p>
                    </div>
                    @if ($quotation->validity_date)
                        <div class="text-right">
                            <span class="text-gray-400 text-xs">Valid until</span>
                            <p class="text-sm text-gray-700">{{ $quotation->validity_date->format('d M Y') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-100">
                <p class="text-sm text-gray-400">No quotations yet.</p>
            </div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <div class="hidden sm:block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3">Quotation #</th>
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Valid Until</th>
                    <th class="px-5 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($quotations as $quotation)
                    @php
                        $statusColor = match($quotation->status->value) {
                            'accepted'  => 'bg-green-100 text-green-700',
                            'rejected'  => 'bg-red-100 text-red-700',
                            'sent'      => 'bg-indigo-100 text-indigo-700',
                            default     => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <span class="font-semibold text-gray-900">{{ $quotation->number ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $quotation->created_at->format('d M Y') }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                                {{ $quotation->status->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500">{{ $quotation->validity_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-right font-medium text-gray-900">{{ \App\Support\Money::format($quotation->total) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-gray-400 text-sm">No quotations yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $quotations->links() }}</div>

</x-portal-app-layout>
