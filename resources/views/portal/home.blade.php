<x-portal-app-layout title="Dashboard">
    @php
        $contact = auth('portal')->user();
        $hour     = now()->timezone('Asia/Kolkata')->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp

    {{-- Hero greeting --}}
    <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-800 px-6 py-7 text-white mb-6 relative overflow-hidden">
        {{-- Decorative circle --}}
        <div class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-white/5"></div>
        <div class="absolute -right-2 bottom-0 w-24 h-24 rounded-full bg-white/5"></div>

        <p class="text-indigo-200 text-sm font-medium mb-1">{{ $greeting }},</p>
        <h1 class="text-2xl font-bold text-white">{{ $contact?->name }} 👋</h1>
        <p class="mt-1 text-indigo-200 text-sm">
            Here's what's happening with
            <span class="font-semibold text-white">{{ $customer->company_name }}</span>'s account.
        </p>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
        {{-- Open Invoices --}}
        <a href="{{ route('portal.invoices.index') }}"
           class="group relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 hover:ring-amber-200 hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 group-hover:text-amber-600 transition-colors">Open Invoices</p>
                    <p class="mt-2 text-4xl font-bold text-gray-900">{{ $openInvoices }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $openInvoices === 1 ? 'invoice' : 'invoices' }} pending</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0 group-hover:bg-amber-100 transition-colors">
                    <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 text-xs font-medium text-amber-600 opacity-0 group-hover:opacity-100 transition-opacity">View invoices →</div>
        </a>

        {{-- Active Projects --}}
        <a href="{{ route('portal.projects.index') }}"
           class="group relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 group-hover:text-indigo-600 transition-colors">Active Projects</p>
                    <p class="mt-2 text-4xl font-bold text-gray-900">{{ $activeProjects }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $activeProjects === 1 ? 'project' : 'projects' }} in progress</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 text-xs font-medium text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity">View projects →</div>
        </a>

        {{-- Open Tickets --}}
        <a href="{{ route('portal.tickets.index') }}"
           class="group relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100 hover:ring-blue-200 hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 group-hover:text-blue-600 transition-colors">Open Tickets</p>
                    <p class="mt-2 text-4xl font-bold text-gray-900">{{ $openTickets }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $openTickets === 1 ? 'ticket' : 'tickets' }} open</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center shrink-0 group-hover:bg-blue-100 transition-colors">
                    <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 text-xs font-medium text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity">View tickets →</div>
        </a>
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
        <a href="{{ route('portal.tickets.create') }}"
           class="flex items-center gap-4 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow-md transition-all group">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0 group-hover:bg-indigo-100 transition-colors">
                <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 transition-colors">Raise a Support Ticket</p>
                <p class="text-xs text-gray-500 mt-0.5">We typically respond within 4 hours</p>
            </div>
        </a>

        @if (config('company.whatsapp'))
        <div class="flex items-center gap-4 rounded-xl bg-green-50 border border-green-200 px-5 py-4">
            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-green-900">Chat with us on WhatsApp</p>
                <p class="text-xs text-green-700 mt-0.5">Faster responses for urgent queries</p>
            </div>
            <x-whatsapp-button label="Chat now" class="shrink-0 text-xs" />
        </div>
        @else
        <a href="{{ route('portal.invoices.index') }}"
           class="flex items-center gap-4 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-gray-100 hover:ring-indigo-200 hover:shadow-md transition-all group">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0 group-hover:bg-amber-100 transition-colors">
                <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 transition-colors">View Payment History</p>
                <p class="text-xs text-gray-500 mt-0.5">Download invoices and check balances</p>
            </div>
        </a>
        @endif
    </div>

    {{-- SSO links to Drishti / SMDost (shown only when the account is connected) --}}
    @if($customer->drishti_client_id || $customer->smdost_client_id)
    <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Your NEDS Tools</h2>
        <div class="flex flex-wrap gap-3">
            @if($customer->drishti_client_id)
            <a href="{{ route('portal.sso', 'drishti') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                </svg>
                Open Drishti Dashboard
            </a>
            @endif
            @if($customer->smdost_client_id)
            <a href="{{ route('portal.sso', 'smdost') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
                Open Social Media Dost
            </a>
            @endif
        </div>
        <p class="mt-2 text-xs text-gray-400">These links sign you in automatically — no separate password needed.</p>
    </div>
    @endif

    {{-- Company info --}}
    <div class="rounded-xl bg-white px-6 py-5 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Your Account</h2>
        <dl class="grid grid-cols-1 gap-x-8 gap-y-2 text-sm text-gray-700 sm:grid-cols-2">
            @if($customer->gstin)
            <div class="flex gap-2"><dt class="text-gray-400 shrink-0">GSTIN</dt><dd class="font-mono text-xs">{{ $customer->gstin }}</dd></div>
            @endif
            @if($customer->email)
            <div class="flex gap-2"><dt class="text-gray-400 shrink-0">Email</dt><dd>{{ $customer->email }}</dd></div>
            @endif
            @if($customer->phone)
            <div class="flex gap-2"><dt class="text-gray-400 shrink-0">Phone</dt><dd>{{ $customer->phone }}</dd></div>
            @endif
            @if($customer->website)
            <div class="flex gap-2"><dt class="text-gray-400 shrink-0">Website</dt><dd>{{ $customer->website }}</dd></div>
            @endif
            @php $address = collect([$customer->address_line1, $customer->city, $customer->state, $customer->pincode])->filter()->join(', '); @endphp
            @if($address)
            <div class="sm:col-span-2 flex gap-2"><dt class="text-gray-400 shrink-0">Address</dt><dd>{{ $address }}</dd></div>
            @endif
        </dl>
    </div>
</x-portal-app-layout>
