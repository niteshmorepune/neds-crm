<x-app-layout>
    <x-slot name="header">{{ $lead->name }}</x-slot>

    <div class="max-w-5xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-gray-900">{{ $lead->name }}</h1>
                        <x-lead-score :lead="$lead" />
                    </div>
                    @if (! is_null($lead->ai_score) && $lead->ai_score_reason)
                        <p class="mt-1 text-xs text-gray-500">AI: {{ $lead->ai_score_reason }}</p>
                    @endif
                    @if ($lead->ai_budget_band || $lead->ai_urgency || $lead->ai_service_fit)
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                            @if ($lead->ai_budget_band)
                                <span><span class="text-gray-400">Budget:</span> {{ $lead->ai_budget_band->label() }}</span>
                            @endif
                            @if ($lead->ai_urgency)
                                <span><span class="text-gray-400">Urgency:</span> {{ $lead->ai_urgency->label() }}</span>
                            @endif
                            @if ($lead->ai_service_fit)
                                <span><span class="text-gray-400">Service fit:</span> {{ $lead->ai_service_fit }}</span>
                            @endif
                        </div>
                    @endif
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                        <div><span class="text-gray-400">Company:</span> {{ $lead->company ?: '—' }}</div>
                        <div><span class="text-gray-400">Status:</span> {{ $lead->status->label() }}</div>
                        <div><span class="text-gray-400">Email:</span> {{ $lead->email ?? '—' }}</div>
                        <div><span class="text-gray-400">Phone:</span> {{ $lead->phone ?? '—' }}</div>
                        @if ($lead->alternate_phone)
                            <div><span class="text-gray-400">Alternate phone:</span> {{ $lead->alternate_phone }}</div>
                        @endif
                        <div><span class="text-gray-400">Source:</span> {{ $lead->source->label() }}</div>
                        @if ($lead->utm_source || $lead->utm_medium || $lead->utm_campaign)
                            <div><span class="text-gray-400">Campaign:</span>
                                {{ collect([$lead->utm_source, $lead->utm_medium, $lead->utm_campaign])->filter()->implode(' / ') }}
                            </div>
                        @endif
                        <div><span class="text-gray-400">Service:</span> {{ $lead->service?->name ?? '—' }}</div>
                        <div><span class="text-gray-400">Est. value:</span> {{ \App\Support\Money::format($lead->estimated_value) }}</div>
                        <div><span class="text-gray-400">Owner:</span> {{ $lead->owner?->name ?? 'Unassigned' }}</div>
                        <div><span class="text-gray-400">Next follow-up:</span>
                            {{ $lead->next_follow_up_at?->timezone(config('app.display_timezone'))->format('d M Y, g:i A') ?? '—' }}</div>
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

                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('leads.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Back</a>
                    <a href="{{ route('calls.create', ['lead_id' => $lead->id]) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Log a call</a>
                    @can('create', \App\Models\Quotation::class)
                        <form method="POST" action="{{ route('leads.quotation', $lead) }}">
                            @csrf
                            @if ($lead->status !== \App\Enums\LeadStatus::Converted)
                                <button type="submit"
                                        class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                                        onclick="return confirm('This lead hasn\'t been converted yet. Creating a quotation will convert them to a client and open a deal first. Continue?')">
                                    Send Quotation
                                </button>
                            @else
                                <button type="submit" class="rounded-md border border-indigo-300 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">
                                    New Quotation
                                </button>
                            @endif
                        </form>
                    @endcan
                    @if ($canConvert)
                        <form method="POST" action="{{ route('leads.convert', $lead) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-500"
                                    onclick="return confirm('Convert this lead into a client and deal?')">Convert</button>
                        </form>
                    @endif
                    @if ($canReassign)
                        <button type="button" x-data @click="$dispatch('open-reassign')" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reassign</button>
                    @endif
                    @can('update', $lead)
                        <a href="{{ route('leads.edit', $lead) }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Edit</a>
                    @endcan
                </div>
            </div>

            @if ($canReassign)
                <div x-data="{ open: false }" x-on:open-reassign.window="open = true" x-show="open" x-cloak class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                    <form method="POST" action="{{ route('leads.reassign', $lead) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <x-input-label for="to_user_id" value="Hand off to" />
                            <select id="to_user_id" name="to_user_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                <option value="">—</option>
                                @foreach ($reassignTargets as $target)
                                    <option value="{{ $target->id }}">{{ $target->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('to_user_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="reason" value="Reason" />
                            <select id="reason" name="reason" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                @foreach ($reassignReasons as $reasonOption)
                                    <option value="{{ $reasonOption->value }}">{{ $reasonOption->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                        </div>
                        <x-primary-button>Confirm reassign</x-primary-button>
                        <button type="button" @click="open = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Notes</h2>
            <livewire:record-notes :record="$lead" :can-manage="$canManage" />
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Calls</h2>
                <a href="{{ route('calls.create', ['lead_id' => $lead->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">+ Log a call</a>
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($lead->callLogs as $call)
                    <li class="py-2">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">{{ $call->direction->label() }} · {{ $call->outcome->label() }}{{ $call->duration_minutes ? " · {$call->duration_minutes}m" : '' }}</span>
                            <span class="text-xs text-gray-400">{{ $call->called_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }} · {{ $call->user?->name }}</span>
                        </div>
                        @if ($call->notes)<p class="mt-1 text-gray-500">{{ $call->notes }}</p>@endif
                    </li>
                @empty
                    <li class="py-2 text-gray-400">No calls logged.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Meet notes</h2>
            <livewire:meeting-import :record="$lead" :can-manage="$canManageMeetings" />
        </div>
    </div>
</x-app-layout>
