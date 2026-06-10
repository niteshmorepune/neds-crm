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
                            <a href="{{ route('clients.show', $deal->customer) }}" class="text-indigo-600 hover:underline">{{ $deal->customer->company_name }}</a>
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
                </dl>

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
                                <p class="mt-1 text-xs text-gray-400">{{ $deal->stage->label() }} is final and cannot be changed.</p>
                            @endif
                            <x-input-error :messages="$errors->get('stage')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="value" value="Value (₹)" />
                            <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$valueRupees" />
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

                        <x-primary-button>Save</x-primary-button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
