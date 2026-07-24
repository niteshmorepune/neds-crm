<x-app-layout>
    <x-slot name="header">Employee Performance Report</x-slot>

    <div class="max-w-7xl mx-auto space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $from->format('Y-m') }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
                <button class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">View</button>
            </form>
            <a href="{{ route('reports.employee-performance.export', ['month' => $from->format('Y-m')]) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Export CSV</a>
        </div>

        <p class="text-sm text-gray-500">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p>

        @livewire('team-performance-summary', ['fromDate' => $from->toDateString(), 'toDate' => $to->toDateString()])

        @livewire('productivity-gap-suggestions', ['rows' => $rows->all()])
    </div>
</x-app-layout>
