<x-app-layout>
    <x-slot name="header">{{ $deal->title }}</x-slot>

    @php
        $valueRupees = old('value', $deal->value !== null ? $deal->value / 100 : null);
        $followUp = old('next_follow_up_at', $deal->next_follow_up_at?->format('Y-m-d\TH:i'));
    @endphp

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">{{ $deal->title }}</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            @if ($deal->customer)
                                <a href="{{ route('clients.show', $deal->customer) }}" class="text-indigo-600 hover:underline">{{ $deal->customer->company_name }}</a>
                            @else
                                Client removed
                            @endif
                            @if ($deal->lead)
                                · from <a href="{{ route('leads.show', $deal->lead) }}" class="text-indigo-600 hover:underline">lead</a>
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('deals.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to board</a>
                </div>

                @if ($deal->stage === \App\Enums\DealStage::Won)
                    <div class="mt-3">
                        @if ($deal->project)
                            <a href="{{ route('projects.show', $deal->project) }}" class="text-sm font-medium text-indigo-600 hover:underline">View project →</a>
                        @elseif (auth()->user()->can('create', \App\Models\Project::class))
                            <form method="POST" action="{{ route('projects.from-deal', $deal) }}">
                                @csrf
                                <button class="rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-500">Create project</button>
                            </form>
                        @endif
                    </div>
                @endif

                <dl class="mt-4 grid grid-cols-2 gap-y-2 text-sm text-gray-600">
                    <div><span class="text-gray-400">Stage:</span> {{ $deal->stage->label() }}</div>
                    <div><span class="text-gray-400">Value:</span> {{ \App\Support\Money::format($deal->value) }}</div>
                    <div><span class="text-gray-400">Service:</span> {{ $deal->service?->name ?? '—' }}</div>
                    <div><span class="text-gray-400">Owner:</span> {{ $deal->owner?->name ?? 'Unassigned' }}</div>
                    <div class="col-span-2">
                        <span class="text-gray-400">Referred by:</span>
                        @if ($deal->partner)
                            <span class="ml-1 inline-flex items-center rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700">{{ $deal->partner->name }}</span>
                        @else
                            <span class="ml-1">Direct</span>
                        @endif
                    </div>
                </dl>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-gray-900">Quotations</h2>
                        @if ($canCreateQuotation && $deal->customer)
                            <a href="{{ route('quotations.create', ['customer_id' => $deal->customer_id, 'deal_id' => $deal->id]) }}"
                               class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                                + New Quotation
                            </a>
                        @endif
                    </div>
                    @forelse ($deal->quotations as $quotation)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0 text-sm">
                            <div>
                                <a href="{{ route('quotations.show', $quotation) }}" class="font-medium text-indigo-600 hover:underline">
                                    {{ $quotation->number ?? 'Draft' }}
                                </a>
                                <span class="ml-2 text-gray-400">{{ $quotation->created_at->format('d M Y') }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-600">{{ \App\Support\Money::format($quotation->total) }}</span>
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-gray-100 text-gray-600'   => $quotation->status === \App\Enums\QuotationStatus::Draft,
                                    'bg-blue-100 text-blue-700'   => $quotation->status === \App\Enums\QuotationStatus::Sent,
                                    'bg-green-100 text-green-800' => $quotation->status === \App\Enums\QuotationStatus::Accepted,
                                    'bg-red-100 text-red-700'     => $quotation->status === \App\Enums\QuotationStatus::Rejected,
                                ])>{{ $quotation->status->label() }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No quotations yet.</p>
                    @endforelse
                </div>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <h2 class="mb-4 text-base font-semibold text-gray-900">Notes</h2>
                    <livewire:record-notes :record="$deal" :can-manage="$canManage" />
                </div>
            </div>

            @can('update', $deal)
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900">Update deal</h2>
                    <form method="POST" action="{{ route('deals.update', $deal) }}" class="mt-4 space-y-4">
                        @csrf @method('PUT')

                        <div>
                            <x-input-label for="title" value="Title" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $deal->title)" />
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="stage" value="Stage" />
                            <select id="stage" name="stage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" @disabled($deal->stage->isTerminal())>
                                @foreach ($stages as $stage)
                                    <option value="{{ $stage->value }}" @selected(old('stage', $deal->stage->value) === $stage->value)>{{ $stage->label() }}</option>
                                @endforeach
                            </select>
                            @if ($deal->stage->isTerminal())
                                <input type="hidden" name="stage" value="{{ $deal->stage->value }}">
                                <p class="mt-1 text-xs text-gray-400">{{ $deal->stage->label() }} is final and cannot be changed.</p>
                            @endif
                            <x-input-error :messages="$errors->get('stage')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="value" value="Value (₹) *" />
                            <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$valueRupees" />
                            <x-input-error :messages="$errors->get('value')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="service_id" value="Service" />
                            <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">—</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected((string) old('service_id', $deal->service_id) === (string) $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="owner_id" value="Owner" />
                            <select id="owner_id" name="owner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach ($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected((string) old('owner_id', $deal->owner_id) === (string) $owner->id)>{{ $owner->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="next_follow_up_at" value="Next follow-up" />
                            <x-text-input id="next_follow_up_at" name="next_follow_up_at" type="datetime-local" class="mt-1 block w-full" :value="$followUp" />
                        </div>
                        <div>
                            <x-input-label for="partner_id" value="Referred by (agency)" />
                            <select id="partner_id" name="partner_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Direct (no agency)</option>
                                @foreach ($partners as $partner)
                                    <option value="{{ $partner->id }}" @selected((string) old('partner_id', $deal->partner_id) === (string) $partner->id)>{{ $partner->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-primary-button>Save</x-primary-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
