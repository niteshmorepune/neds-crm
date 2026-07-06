<x-app-layout>
    <x-slot name="header">Client Radar</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        <p class="text-sm text-gray-500">
            Active clients flagged for a check-in: no recent contact, declining activity, an overdue invoice, or a
            single-service upsell opportunity. Computed live from CRM data — nothing here is stored or auto-actioned.
        </p>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Signals</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $row)
                        @php($customer = $row['customer'])
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('clients.show', $customer) }}" class="text-indigo-600 hover:underline">
                                    {{ $customer->company_name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $customer->owner?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($row['flags'] as $key => $flag)
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                            'bg-red-100 text-red-700' => in_array($key, ['no_contact', 'declining_activity']),
                                            'bg-amber-100 text-amber-700' => $key === 'overdue_invoice',
                                            'bg-emerald-100 text-emerald-700' => $key === 'upsell_opportunity',
                                        ])>
                                            {{ $flag['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                                <ul class="mt-1.5 space-y-0.5 text-xs text-gray-500">
                                    @foreach ($row['flags'] as $flag)
                                        <li>{{ $flag['detail'] }}</li>
                                    @endforeach
                                </ul>

                                <div class="mt-2">
                                    @livewire('client-radar-suggestion', ['customerId' => $customer->id, 'flags' => $row['flags']], key('radar-suggestion-'.$customer->id))
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No clients need attention right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
