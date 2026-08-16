<x-partner-portal-app-layout header="Dashboard">

    <div class="mb-8 rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-700 p-6 text-white">
        <p class="text-indigo-100">Good to see you,</p>
        <h2 class="text-2xl font-bold">{{ $partner->name }} 👋</h2>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Your Referred Clients</h3>
            <p class="mt-1 text-sm text-gray-500">Clients you've referred to {{ config('company.name') }}.</p>

            <div class="mt-4 divide-y divide-gray-100">
                @forelse ($referredCustomers as $customer)
                    <div class="flex items-center justify-between py-3">
                        <span class="text-sm font-medium text-gray-900">{{ $customer->company_name }}</span>
                        <span class="text-xs text-gray-400">{{ $customer->status->label() }}</span>
                    </div>
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
                    <p class="py-6 text-center text-sm text-gray-400">No content submissions yet.</p>
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
