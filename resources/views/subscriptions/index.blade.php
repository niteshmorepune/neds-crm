<x-app-layout>
    <x-slot name="header">Subscriptions</x-slot>

    <div class="max-w-5xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            Internal tool/vendor subscriptions (Claude, hosting, domains, ...). You'll get an
            email and a bell notification before each one renews.
        </p>

        <div class="flex items-center justify-end">
            <a href="{{ route('subscriptions.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">+ New Subscription</a>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3 text-right">Cost / cycle</th>
                        <th class="px-4 py-3">Cycle</th>
                        <th class="px-4 py-3">Renews</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($subscriptions as $subscription)
                        @php
                            $daysUntil = now()->startOfDay()->diffInDays($subscription->renewal_date, false);
                            $status = match (true) {
                                ! $subscription->is_active => ['label' => 'Inactive', 'classes' => 'bg-gray-100 text-gray-600'],
                                $daysUntil < 0 => ['label' => 'Overdue', 'classes' => 'bg-red-50 text-red-700'],
                                $daysUntil <= $subscription->reminder_days_before => ['label' => 'Renewing soon', 'classes' => 'bg-amber-50 text-amber-700'],
                                default => ['label' => 'Active', 'classes' => 'bg-emerald-50 text-emerald-700'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $subscription->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $subscription->vendor ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ \App\Support\Money::format($subscription->cost) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $subscription->billing_cycle->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $subscription->renewal_date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $status['classes'] }}">{{ $status['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('subscriptions.edit', $subscription) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('subscriptions.destroy', $subscription) }}" class="inline ml-3"
                                      onsubmit="return confirm('Remove this subscription?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:text-red-500">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No subscriptions tracked yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
