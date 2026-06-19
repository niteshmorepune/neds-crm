<x-portal-app-layout header="Dashboard">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('portal.invoices.index') }}"
           class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow transition-all group">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 group-hover:text-indigo-500">Open Invoices</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $openInvoices }}</div>
        </a>
        <a href="{{ route('portal.projects.index') }}"
           class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow transition-all group">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 group-hover:text-indigo-500">Active Projects</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $activeProjects }}</div>
        </a>
        <a href="{{ route('portal.tickets.index') }}"
           class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow transition-all group">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 group-hover:text-indigo-500">Open Tickets</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $openTickets }}</div>
        </a>
    </div>

    @if (config('company.whatsapp'))
    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 rounded-xl bg-green-50 border border-green-200 px-6 py-4">
        <div>
            <p class="font-semibold text-green-900 text-sm">Need help? We're on WhatsApp.</p>
            <p class="text-xs text-green-700 mt-0.5">Chat with our after-sale support team instantly.</p>
        </div>
        <x-whatsapp-button label="Chat with us" class="shrink-0" />
    </div>
    @endif

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-base font-semibold text-gray-900">{{ $customer->company_name }}</h2>
        <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-2 text-sm text-gray-600 sm:grid-cols-2">
            <div><span class="font-medium text-gray-400">GSTIN</span><span class="ml-2">{{ $customer->gstin ?? '—' }}</span></div>
            <div><span class="font-medium text-gray-400">Email</span><span class="ml-2">{{ $customer->email ?? '—' }}</span></div>
            <div><span class="font-medium text-gray-400">Phone</span><span class="ml-2">{{ $customer->phone ?? '—' }}</span></div>
            <div><span class="font-medium text-gray-400">Website</span><span class="ml-2">{{ $customer->website ?? '—' }}</span></div>
            <div class="sm:col-span-2">
                <span class="font-medium text-gray-400">Address</span>
                <span class="ml-2">{{ collect([$customer->address_line1, $customer->city, $customer->state, $customer->pincode])->filter()->join(', ') ?: '—' }}</span>
            </div>
        </dl>
    </div>
</x-portal-app-layout>
