<x-partner-portal-app-layout header="Dashboard">

    <div class="mb-8 rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-700 p-6 text-white">
        <p class="text-indigo-100">Good to see you,</p>
        <h2 class="text-2xl font-bold">{{ $partner->name }} 👋</h2>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-500">Total outstanding</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ \App\Support\Money::format($outstandingTotal) }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $partnerAccount ? 'Overdue' : 'Clients with something overdue' }}</p>
            <p class="mt-1 text-xl font-semibold {{ $overdueClientCount > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $overdueClientCount }}</p>
        </div>
    </div>

    {{-- Portfolio summary: at-a-glance status of everything referred through this partner. --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
        <div class="rounded-lg bg-emerald-50 px-4 py-3">
            <p class="text-xs font-medium text-emerald-700">Active clients</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $clientStatusCounts->get('active', 0) }}</p>
        </div>
        <div class="rounded-lg bg-yellow-100 px-4 py-3">
            <p class="text-xs font-medium text-yellow-700">Prospect clients</p>
            <p class="mt-1 text-2xl font-semibold text-yellow-800">{{ $clientStatusCounts->get('prospect', 0) }}</p>
        </div>
        <div class="rounded-lg bg-gray-100 px-4 py-3">
            <p class="text-xs font-medium text-gray-600">Inactive clients</p>
            <p class="mt-1 text-2xl font-semibold text-gray-800">{{ $clientStatusCounts->get('inactive', 0) }}</p>
        </div>
        <div class="rounded-lg bg-amber-50 px-4 py-3">
            <p class="text-xs font-medium text-amber-700">Services on hold</p>
            <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $servicesOnHoldCount }}</p>
        </div>
        <div class="rounded-lg bg-indigo-50 px-4 py-3">
            <p class="text-xs font-medium text-indigo-700">Quotations accepted</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ $quotationStatusCounts->get('accepted', 0) }} <span class="text-sm font-normal text-indigo-700">/ {{ $quotationStatusCounts->sum() }}</span></p>
        </div>
    </div>

    @if ($partnerAccount)
        <div class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Your Account — {{ $partnerAccount['customer']->company_name }}</h3>
            <p class="mt-1 text-sm text-gray-500">Your referred clients are billed to you as one consolidated account, not individually — this is your real invoice history and balance with {{ config('company.name') }}.</p>

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
                            <tr><td colspan="5" class="py-6 text-center text-gray-400">No invoices on your account yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Your Referred Clients</h3>
            <p class="mt-1 text-sm text-gray-500">Clients you've referred to {{ config('company.name') }}.</p>

            <div class="mt-4 divide-y divide-gray-100">
                @forelse ($referredCustomers as $row)
                    <a href="{{ route('partner-portal.clients.show', $row->customer) }}" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded">
                        <div>
                            <span class="text-sm font-medium text-gray-900">{{ $row->customer->company_name }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ $row->customer->status->label() }}</span>
                        </div>
                        <div class="text-right">
                            @if ($partnerAccount)
                                <span class="text-xs text-gray-400">Billed via your account</span>
                            @else
                                <span class="text-sm font-medium {{ $row->overdue_count > 0 ? 'text-red-700' : 'text-gray-700' }}">{{ \App\Support\Money::format($row->outstanding_amount) }}</span>
                                @if ($row->overdue_count > 0)
                                    <span class="ml-1 text-xs text-red-600">({{ $row->overdue_count }} overdue)</span>
                                @endif
                            @endif
                        </div>
                    </a>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">No referred clients yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Your Content Submissions</h3>
            <p class="mt-1 text-sm text-gray-500">Content pieces you're collaborating on with {{ config('company.name') }}.</p>

            <div class="mt-4 divide-y divide-gray-100">
                @forelse ($contentPieces as $piece)
                    <div class="py-4" x-data="{ uploading: false }">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $piece->title }}</p>
                                <p class="text-xs text-gray-400">{{ $piece->platform->label() }}@if ($piece->project) · {{ $piece->project->name }} @endif</p>
                            </div>
                            <span class="shrink-0 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $piece->status->badgeClass() }}">
                                {{ $piece->status->label() }}
                            </span>
                        </div>

                        <button type="button" @click="uploading = !uploading" class="mt-2 text-xs text-indigo-600 hover:text-indigo-500">
                            <span x-show="!uploading">Upload files</span>
                            <span x-show="uploading" style="display:none">Cancel</span>
                        </button>

                        <form method="POST" action="{{ route('partner-portal.content-pieces.upload', $piece) }}" enctype="multipart/form-data"
                              x-show="uploading" style="display:none" class="mt-3 space-y-2">
                            @csrf
                            <input type="file" name="files[]" multiple accept="image/*,video/*,.pdf"
                                   class="block w-full text-sm text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
                            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                                Upload
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">
                        No content submissions yet — we'll open a submission here as soon as a piece of content is ready for you to upload against. Nothing to do on your side until then.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
        <h3 class="text-base font-semibold text-gray-900">Quotations</h3>
        <p class="mt-1 text-sm text-gray-500">Quotations shared with you for your referred clients — download the PDF anytime.</p>

        <div class="mt-4 divide-y divide-gray-100">
            @forelse ($quotations as $quotation)
                <div class="flex items-center justify-between py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $quotation->customer?->company_name ?? 'Client removed' }}</p>
                        <p class="text-xs text-gray-400">{{ $quotation->number ?? '—' }} · {{ $quotation->status->label() }} · {{ \App\Support\Money::format($quotation->total) }}</p>
                    </div>
                    <a href="{{ route('partner-portal.quotations.pdf', $quotation) }}" class="shrink-0 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                        Download PDF
                    </a>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">No quotations shared yet.</p>
            @endforelse
        </div>
    </div>

    @if ($commissionEstimate || $commissionHistory->isNotEmpty())
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Your Earnings</h3>
            <p class="mt-1 text-sm text-gray-500">Commission on clients you've referred, earned when their deal is won.</p>

            @if ($commissionEstimate)
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-md border border-gray-200 p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">This month's referrals</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ \App\Support\Money::format($commissionEstimate['referred_value']) }}</p>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Estimated commission</p>
                        <p class="mt-1 text-lg font-semibold text-indigo-600">{{ \App\Support\Money::format($commissionEstimate['commission_amount']) }}</p>
                    </div>
                </div>
            @endif

            @if ($commissionHistory->isNotEmpty())
                <div class="mt-4 divide-y divide-gray-100">
                    @foreach ($commissionHistory as $statement)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $statement->period_start->format('F Y') }}</p>
                                <p class="text-xs text-gray-400">{{ \App\Support\Money::format($statement->referred_value) }} referred</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ \App\Support\Money::format($statement->commission_amount) }}</p>
                                @if ($statement->isPaid())
                                    <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Paid {{ $statement->paid_at->format('d M Y') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Unpaid</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

</x-partner-portal-app-layout>
