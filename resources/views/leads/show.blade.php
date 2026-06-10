<x-app-layout>
    <x-slot name="header">{{ $lead->name }}</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $lead->name }}</h1>
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">Company:</span> {{ $lead->company ?: '—' }}</div>
                        <div><span class="text-gray-400">Status:</span> {{ $lead->status->label() }}</div>
                        <div><span class="text-gray-400">Email:</span> {{ $lead->email ?? '—' }}</div>
                        <div><span class="text-gray-400">Phone:</span> {{ $lead->phone ?? '—' }}</div>
                        <div><span class="text-gray-400">Source:</span> {{ $lead->source->label() }}</div>
                        <div><span class="text-gray-400">Service:</span> {{ $lead->service?->name ?? '—' }}</div>
                        <div><span class="text-gray-400">Est. value:</span> {{ \App\Support\Money::format($lead->estimated_value) }}</div>
                        <div><span class="text-gray-400">Owner:</span> {{ $lead->owner?->name ?? 'Unassigned' }}</div>
                        <div><span class="text-gray-400">Next follow-up:</span>
                            {{ $lead->next_follow_up_at?->timezone(config('app.timezone'))->format('d M Y, g:i A') ?? '—' }}</div>
                    </dl>

                    @if ($lead->convertedCustomer)
                        <div class="mt-3 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">
                            Converted →
                            <a href="{{ route('clients.show', $lead->convertedCustomer) }}" class="font-medium underline">{{ $lead->convertedCustomer->company_name }}</a>
                            @if ($lead->convertedDeal)
                                · <a href="{{ route('deals.show', $lead->convertedDeal) }}" class="font-medium underline">View deal</a>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('leads.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
                    <a href="{{ route('calls.create', ['lead_id' => $lead->id]) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Log a call</a>
                    @if ($canConvert)
                        <form method="POST" action="{{ route('leads.convert', $lead) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-500"
                                    onclick="return confirm('Convert this lead into a client and deal?')">Convert</button>
                        </form>
                    @endif
                    @can('update', $lead)
                        <a href="{{ route('leads.edit', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Edit</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Notes</h2>
            <livewire:record-notes :record="$lead" :can-manage="$canManage" />
        </div>
    </div>
</x-app-layout>
