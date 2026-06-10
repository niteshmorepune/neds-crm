<x-portal-app-layout header="Company Profile">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('portal.invoices.index') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
            <div class="text-sm text-gray-500">Open invoices</div>
            <div class="text-2xl font-semibold text-gray-900">{{ $openInvoices }}</div>
        </a>
        <a href="{{ route('portal.projects.index') }}" class="rounded-lg bg-white p-5 shadow-sm hover:shadow">
            <div class="text-sm text-gray-500">Active projects</div>
            <div class="text-2xl font-semibold text-gray-900">{{ $activeProjects }}</div>
        </a>
        <div class="rounded-lg bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">Open tickets</div>
            <div class="text-2xl font-semibold text-gray-900">{{ $openTickets }}</div>
        </div>
    </div>

    <div class="mt-6 rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900">{{ $customer->company_name }}</h2>
        <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
            <div><span class="text-gray-400">GSTIN:</span> {{ $customer->gstin ?? '—' }}</div>
            <div><span class="text-gray-400">Email:</span> {{ $customer->email ?? '—' }}</div>
            <div><span class="text-gray-400">Phone:</span> {{ $customer->phone ?? '—' }}</div>
            <div><span class="text-gray-400">Website:</span> {{ $customer->website ?? '—' }}</div>
            <div class="sm:col-span-2"><span class="text-gray-400">Address:</span>
                {{ collect([$customer->address_line1, $customer->city, $customer->state, $customer->pincode])->filter()->join(', ') ?: '—' }}</div>
        </dl>
    </div>
</x-portal-app-layout>
