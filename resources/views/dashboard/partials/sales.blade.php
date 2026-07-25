<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Follow-ups due</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['followups_due']) }}</p>
    </div>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Won this month</p>
        <p class="mt-2 text-3xl font-semibold text-green-600">{{ \App\Support\Money::format($stats['won_this_month_value']) }}</p>
    </div>
</div>

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h3 class="text-base font-semibold text-gray-900">Open pipeline by stage</h3>
    <table class="mt-4 min-w-full text-sm">
        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
            <tr><th class="py-2">Stage</th><th class="py-2 text-right">Deals</th><th class="py-2 text-right">Value</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($stats['pipeline'] as $row)
                <tr>
                    <td class="py-2 text-gray-700">{{ $row['stage'] }}</td>
                    <td class="py-2 text-right text-gray-600">{{ $row['deals'] }}</td>
                    <td class="py-2 text-right text-gray-900">{{ \App\Support\Money::format($row['value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="py-6 text-center text-gray-400">No open deals.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<livewire:overdue-follow-ups />

<livewire:my-productivity />
