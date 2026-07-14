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
            <h3 class="text-base font-semibold text-gray-900">Client health</h3>
            <p class="mt-1 text-sm text-gray-500">Which of this partner's clients need a collections follow-up or are ready for the next milestone invoice.</p>
            <div class="mt-3">
                @include('collections._client-table', ['rows' => $rows, 'showPartnerColumn' => false])
            </div>
        </div>
    </div>
</x-app-layout>
