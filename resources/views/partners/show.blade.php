<x-app-layout>
    <x-slot name="header">{{ $partner->name }}</x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold text-gray-900">{{ $partner->name }}</h1>
                        @if ($partner->portal_enabled)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                {{ $partner->password_set_at ? 'Portal active' : 'Portal invited' }}
                            </span>
                        @endif
                    </div>
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
                        @if ($partner->portal_enabled)
                            <form method="POST" action="{{ route('partners.revoke', $partner) }}" onsubmit="return confirm('Revoke partner portal access?')">
                                @csrf
                                <button type="submit" class="rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">Revoke portal</button>
                            </form>
                        @elseif ($partner->email)
                            <form method="POST" action="{{ route('partners.invite', $partner) }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Invite to portal</button>
                            </form>
                        @else
                            <span class="text-xs italic text-gray-400">Add email to enable portal invite</span>
                        @endif
                        <a href="{{ route('partners.edit', $partner) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                </div>
            </div>
        </div>

        @if ($partnerAccount)
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-900">Your Account — {{ $partnerAccount['customer']->company_name }}</h3>
                    <p class="text-sm text-gray-500">Outstanding: <span class="font-semibold text-gray-900">{{ \App\Support\Money::format($partnerAccount['outstanding_amount']) }}</span></p>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    This is a reseller partner — referred clients are GST-billed to this one consolidated account
                    (<a href="{{ route('clients.show', $partnerAccount['customer']) }}" class="text-indigo-600 hover:underline">{{ $partnerAccount['customer']->company_name }}</a>)
                    instead of individually, so "Billed — last 6 months" below is empty for each referred client by design.
                    @if ($partnerAccount['overdue_count'] > 0)
                        <span class="font-medium text-red-700">{{ $partnerAccount['overdue_count'] }} invoice(s) overdue.</span>
                    @endif
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="py-2">Invoice</th>
                                <th class="py-2">Status</th>
                                <th class="py-2">Due date</th>
                                <th class="py-2 text-right">Amount</th>
                                <th class="py-2 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($partnerAccount['invoices'] as $invoice)
                                <tr>
                                    <td class="py-2 font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                                    <td class="py-2 text-gray-600">{{ $invoice->status->label() }}</td>
                                    <td class="py-2 text-gray-600">{{ $invoice->status === \App\Enums\InvoiceStatus::Paid ? '—' : ($invoice->due_date?->format('d M Y') ?? '—') }}</td>
                                    <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($invoice->total) }}</td>
                                    <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($invoice->balance()) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-400">No invoices on this account yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($commissionEstimate || $commissionHistory->isNotEmpty())
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-900">Commission</h3>
                    <span class="text-sm text-gray-500">Rate: <span class="font-semibold text-gray-900">{{ rtrim(rtrim(number_format((float) $partner->commission_rate, 2), '0'), '.') }}%</span></span>
                </div>

                @if ($commissionEstimate)
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500">This month's referrals</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ \App\Support\Money::format($commissionEstimate['referred_value']) }}</p>
                        </div>
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Estimated commission</p>
                            <p class="mt-1 text-lg font-semibold text-indigo-600">{{ \App\Support\Money::format($commissionEstimate['commission_amount']) }}</p>
                        </div>
                        <div class="rounded-md border border-gray-200 p-3">
                            <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                            <p class="mt-1 text-sm text-gray-500">Live estimate — finalized on the 1st of next month</p>
                        </div>
                    </div>
                @endif

                @if ($commissionHistory->isNotEmpty())
                    <h4 class="mt-5 text-sm font-medium text-gray-700">Statement history</h4>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="py-2">Month</th>
                                    <th class="py-2 text-right">Referred value</th>
                                    <th class="py-2 text-right">Rate</th>
                                    <th class="py-2 text-right">Commission</th>
                                    <th class="py-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($commissionHistory as $statement)
                                    <tr>
                                        <td class="py-2 font-medium text-gray-900">{{ $statement->period_start->format('F Y') }}</td>
                                        <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($statement->referred_value) }}</td>
                                        <td class="py-2 text-right text-gray-700">{{ rtrim(rtrim(number_format((float) $statement->commission_rate, 2), '0'), '.') }}%</td>
                                        <td class="py-2 text-right font-medium text-gray-900">{{ \App\Support\Money::format($statement->commission_amount) }}</td>
                                        <td class="py-2 text-right">
                                            @if ($statement->isPaid())
                                                <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Paid {{ $statement->paid_at->format('d M Y') }}</span>
                                            @else
                                                @can('update', $partner)
                                                    <form method="POST" action="{{ route('partners.commission-statements.mark-paid', [$partner, $statement]) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Mark as Paid</button>
                                                    </form>
                                                @else
                                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Unpaid</span>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-900">Billed — last 6 months</h3>
                <p class="text-sm text-gray-500">Total: <span class="font-semibold text-gray-900">{{ \App\Support\Money::format($billed->sum('amount')) }}</span></p>
            </div>
            <p class="mt-1 text-sm text-gray-500">Every referred client, including ones billed nothing in the window — invoiced (issued) amounts, not just what's been paid.</p>

            <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($billedByMonth as $m)
                    <div class="rounded-md border border-gray-200 p-3 text-center">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $m['label'] }}</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ \App\Support\Money::format($m['amount']) }}</p>
                        <p class="text-xs text-gray-400">{{ $m['invoice_count'] }} invoice(s)</p>
                    </div>
                @endforeach
            </div>

            <h4 class="mt-5 text-sm font-medium text-gray-700">By client</h4>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2">Client</th>
                            <th class="py-2 text-right">Invoices</th>
                            <th class="py-2 text-right">Billed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($billed as $b)
                            <tr>
                                <td class="py-2 text-gray-900">
                                    <a href="{{ route('clients.show', $b['customer']) }}" class="font-medium text-indigo-600 hover:underline">
                                        {{ $b['customer']->company_name }}
                                    </a>
                                </td>
                                <td class="py-2 text-right text-gray-600">{{ $b['invoice_count'] }}</td>
                                <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($b['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-400">No referred clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900">Quotations</h3>
            <p class="mt-1 text-sm text-gray-500">Every quotation for this partner's referred clients — proposal stage, before any invoice.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2">Number</th>
                            <th class="py-2">Client</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 text-right">Amount</th>
                            <th class="py-2">Date</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($quotations as $quotation)
                            <tr>
                                <td class="py-2 font-medium text-gray-900">{{ $quotation->number ?? '—' }}</td>
                                <td class="py-2 text-gray-600">{{ $quotation->customer?->company_name ?? 'Client removed' }}</td>
                                <td class="py-2 text-gray-600">{{ $quotation->status->label() }}</td>
                                <td class="py-2 text-right text-gray-700">{{ \App\Support\Money::format($quotation->total) }}</td>
                                <td class="py-2 text-gray-600">{{ $quotation->created_at->format('d M Y') }}</td>
                                <td class="py-2 text-right"><a href="{{ route('quotations.show', $quotation) }}" class="text-indigo-600 hover:underline">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-400">No quotations for this partner's referred clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
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
