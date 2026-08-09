<x-app-layout>
    <x-slot name="header">Revenue at Risk</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        <div class="rounded-lg bg-white p-6 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total at risk</p>
            <p class="mt-1 text-3xl font-semibold text-red-700">{{ \App\Support\Money::format($total) }}</p>
            <p class="mt-2 text-xs text-gray-400">Sum of the three buckets below — a client with both an overdue invoice and a Client Radar flag counts in more than one, so this total isn't deduplicated. Click a bucket to see exactly what's in it.</p>
        </div>

        <div class="space-y-3">
            @foreach ($signals as $signal)
                <a href="{{ $signal['route'] }}" class="flex items-center justify-between gap-4 rounded-lg bg-white p-5 shadow-sm transition hover:shadow-md">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $signal['label'] }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $signal['detail'] }}</p>
                    </div>
                    <p class="shrink-0 text-xl font-semibold tabular-nums text-gray-900">{{ \App\Support\Money::format($signal['amount']) }}</p>
                </a>
            @endforeach
        </div>
    </div>
</x-app-layout>
