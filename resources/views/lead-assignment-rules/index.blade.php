<x-app-layout>
    <x-slot name="header">Lead Assignment Rules</x-slot>

    <div class="max-w-4xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            New leads are auto-assigned to whichever active Sales rep currently has the fewest open leads.
            A rule below overrides that for leads matching a specific Meta ad campaign name, or a whole service line —
            a campaign match wins over a service match when both could apply. A VA-Paid rule instead routes a lead
            the moment it pays for the Visibility Audit offer, but only while it's still unowned — it never
            reassigns a lead that already has an owner.
        </p>

        {{-- Add a rule --}}
        <div class="rounded-lg bg-white p-6 shadow-sm" x-data="{ matchType: '{{ old('match_type', 'campaign') }}' }">
            <h2 class="text-base font-semibold text-gray-900">Add a rule</h2>
            <form method="POST" action="{{ route('lead-assignment-rules.store') }}" class="mt-3 space-y-3">
                @csrf
                <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                    <label class="flex items-center gap-1.5">
                        <input type="radio" name="match_type" value="campaign" x-model="matchType" class="border-gray-300 text-indigo-600" @checked(old('match_type', 'campaign') === 'campaign')>
                        Match by campaign name
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input type="radio" name="match_type" value="service" x-model="matchType" class="border-gray-300 text-indigo-600" @checked(old('match_type') === 'service')>
                        Match by service
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input type="radio" name="match_type" value="va_paid" x-model="matchType" class="border-gray-300 text-indigo-600" @checked(old('match_type') === 'va_paid')>
                        Match Visibility Audit — Paid
                    </label>
                </div>

                <p x-show="matchType === 'va_paid'" class="text-xs text-gray-400">
                    Applies the moment a lead pays for the Visibility Audit offer, only if it has no owner yet.
                </p>

                <div x-show="matchType === 'campaign'">
                    <x-input-label for="utm_campaign" value="Campaign name (exact match, e.g. CRM-ERP-Pune-Aug2026-V1)" />
                    <x-text-input id="utm_campaign" name="utm_campaign" type="text" class="mt-1 block w-full" :value="old('utm_campaign')" />
                    <x-input-error :messages="$errors->get('utm_campaign')" class="mt-1" />
                </div>

                <div x-show="matchType === 'service'">
                    <x-input-label for="service_id" value="Service" />
                    <select id="service_id" name="service_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((string) old('service_id') === (string) $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('service_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="assigned_user_id" value="Assign matching leads to *" />
                    <select id="assigned_user_id" name="assigned_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">—</option>
                        @foreach ($salesUsers as $salesUser)
                            <option value="{{ $salesUser->id }}" @selected((string) old('assigned_user_id') === (string) $salesUser->id)>{{ $salesUser->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Only active Sales users can be a rule's target.</p>
                    <x-input-error :messages="$errors->get('assigned_user_id')" class="mt-1" />
                </div>

                <x-primary-button>Add rule</x-primary-button>
            </form>
        </div>

        {{-- Existing rules --}}
        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Matches</th>
                        <th class="px-4 py-3">Assign to</th>
                        <th class="px-4 py-3 w-24">Active</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rules as $rule)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($rule->utm_campaign)
                                    <span class="text-gray-900">Campaign:</span> {{ $rule->utm_campaign }}
                                @elseif ($rule->va_paid)
                                    <span class="text-gray-900">Visibility Audit</span> — Paid
                                @else
                                    <span class="text-gray-900">Service:</span> {{ $rule->service?->name ?? 'Service removed' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <select form="rule-update-{{ $rule->id }}" name="assigned_user_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @foreach ($salesUsers as $salesUser)
                                        <option value="{{ $salesUser->id }}" @selected($rule->assigned_user_id === $salesUser->id)>{{ $salesUser->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input form="rule-update-{{ $rule->id }}" type="checkbox" name="active" value="1" @checked($rule->active) class="rounded border-gray-300 text-indigo-600" />
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <button form="rule-update-{{ $rule->id }}" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">Save</button>
                                <button form="rule-delete-{{ $rule->id }}" class="text-xs text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No assignment rules yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400">
            To change what a rule matches (its campaign name or service), delete it and add a new one — only the
            assigned rep and active status can be edited in place.
        </p>

        {{-- One update + delete form per row. The match itself (utm_campaign/service_id/match_type) is carried
             through unchanged as hidden fields — only assigned_user_id/active are actually editable here. --}}
        @foreach ($rules as $rule)
            <form id="rule-update-{{ $rule->id }}" method="POST" action="{{ route('lead-assignment-rules.update', $rule) }}" class="hidden">
                @csrf @method('PUT')
                <input type="hidden" name="match_type" value="{{ $rule->utm_campaign ? 'campaign' : ($rule->va_paid ? 'va_paid' : 'service') }}">
                <input type="hidden" name="utm_campaign" value="{{ $rule->utm_campaign }}">
                <input type="hidden" name="service_id" value="{{ $rule->service_id }}">
            </form>
            <form id="rule-delete-{{ $rule->id }}" method="POST" action="{{ route('lead-assignment-rules.destroy', $rule) }}" class="hidden" onsubmit="return confirm('Remove this rule?')">@csrf @method('DELETE')</form>
        @endforeach
    </div>
</x-app-layout>
