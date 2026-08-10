<x-portal-app-layout :header="'Quotation '.($quotation->number ?? '#'.$quotation->id)">

    @php
        $statusColor = match($quotation->status->value) {
            'accepted'  => 'bg-green-100 text-green-700',
            'rejected'  => 'bg-red-100 text-red-700',
            'sent'      => 'bg-indigo-100 text-indigo-700',
            default     => 'bg-gray-100 text-gray-600',
        };
    @endphp

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Created {{ $quotation->created_at->format('d M Y') }}
                @if ($quotation->validity_date) · Valid until {{ $quotation->validity_date->format('d M Y') }} @endif
            </p>
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                {{ $quotation->status->label() }}
            </span>
        </div>

        @if ($quotation->scope_of_work)
            <p class="mt-4 text-sm text-gray-700">{{ $quotation->scope_of_work }}</p>
        @endif

        <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
            <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                <tr><th class="py-2">Description</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Rate</th><th class="py-2 text-right">Amount</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($quotation->items as $item)
                    <tr>
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                        <td class="py-2 text-right">{{ \App\Support\Money::format($item->rate) }}</td>
                        <td class="py-2 text-right">{{ \App\Support\Money::format($item->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex justify-end">
            <dl class="w-56 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Total</dt><dd class="font-semibold">{{ \App\Support\Money::format($quotation->total) }}</dd></div>
            </dl>
        </div>

        @if ($quotation->terms)
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Terms</p>
                <p class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $quotation->terms }}</p>
            </div>
        @endif

        @if ($quotation->status->value === 'sent')
            <div class="mt-6 border-t border-gray-100 pt-6" x-data="{ rejecting: false }">
                <div class="flex flex-col sm:flex-row gap-3" x-show="!rejecting">
                    <form method="POST" action="{{ route('portal.quotations.accept', $quotation) }}">
                        @csrf
                        <button type="submit" class="w-full sm:w-auto rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            Accept Quotation
                        </button>
                    </form>

                    <button type="button" @click="rejecting = true"
                            class="w-full sm:w-auto rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                        Reject Quotation
                    </button>
                </div>

                <form method="POST" action="{{ route('portal.quotations.reject', $quotation) }}" x-show="rejecting" style="display:none" class="flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text" name="client_decision_note" maxlength="1000" placeholder="Optional — let us know why (helps us follow up)"
                           class="w-full flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500">
                    <div class="flex gap-2">
                        <button type="submit" class="shrink-0 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Confirm Rejection
                        </button>
                        <button type="button" @click="rejecting = false" class="shrink-0 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        @elseif ($quotation->client_decision_note)
            <div class="mt-6 border-t border-gray-100 pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Your note</p>
                <p class="mt-1 text-sm text-gray-700">{{ $quotation->client_decision_note }}</p>
            </div>
        @endif
    </div>

    <div class="mt-4"><a href="{{ route('portal.quotations.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to quotations</a></div>
</x-portal-app-layout>
