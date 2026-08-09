<x-app-layout>
    <x-slot name="header">Merge Leads</x-slot>

    <div class="max-w-4xl mx-auto space-y-4">
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <p class="text-sm text-gray-500">
            Pick which record survives, then choose which lead's value to keep for each field. Notes, call logs,
            meetings, and activity history from the other lead move onto the survivor; the other lead is then
            archived (soft-deleted), not deleted outright.
        </p>

        <form method="POST" action="{{ route('leads.merge.store') }}" x-data="{ primary: '{{ $leadA->id }}' }" class="space-y-4">
            @csrf
            <input type="hidden" name="primary_id" :value="primary" />
            <input type="hidden" name="duplicate_id" :value="primary === '{{ $leadA->id }}' ? '{{ $leadB->id }}' : '{{ $leadA->id }}'" />

            <div class="grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3"
                       :class="primary === '{{ $leadA->id }}' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-white'">
                    <input type="radio" x-model="primary" value="{{ $leadA->id }}" class="text-indigo-600" />
                    <span class="text-sm font-medium text-gray-900">Keep record: {{ $leadA->name }} (#{{ $leadA->id }})</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3"
                       :class="primary === '{{ $leadB->id }}' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-white'">
                    <input type="radio" x-model="primary" value="{{ $leadB->id }}" class="text-indigo-600" />
                    <span class="text-sm font-medium text-gray-900">Keep record: {{ $leadB->name }} (#{{ $leadB->id }})</span>
                </label>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Field</th>
                            <th class="px-4 py-3">{{ $leadA->name }} (#{{ $leadA->id }})</th>
                            <th class="px-4 py-3">{{ $leadB->name }} (#{{ $leadB->id }})</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $labels = [
                                'name' => 'Name', 'company' => 'Company', 'phone' => 'Phone', 'email' => 'Email',
                                'source' => 'Source', 'service_id' => 'Service', 'estimated_value' => 'Est. value',
                                'owner_id' => 'Owner', 'status' => 'Status',
                            ];
                            $display = function ($lead, string $field) {
                                return match ($field) {
                                    'service_id' => $lead->service?->name ?? '—',
                                    'estimated_value' => \App\Support\Money::format($lead->estimated_value),
                                    'owner_id' => $lead->owner?->name ?? 'Unassigned',
                                    'source' => $lead->source->label(),
                                    'status' => $lead->status->label(),
                                    default => $lead->{$field} ?: '—',
                                };
                            };
                        @endphp
                        @foreach ($fields as $field)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ $labels[$field] }}</td>
                                <td class="px-4 py-3">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="field_source[{{ $field }}]" value="{{ $leadA->id }}"
                                               x-bind:checked="primary === '{{ $leadA->id }}'"
                                               class="text-indigo-600" />
                                        <span>{{ $display($leadA, $field) }}</span>
                                    </label>
                                </td>
                                <td class="px-4 py-3">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="field_source[{{ $field }}]" value="{{ $leadB->id }}"
                                               x-bind:checked="primary === '{{ $leadB->id }}'"
                                               class="text-indigo-600" />
                                        <span>{{ $display($leadB, $field) }}</span>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('leads.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                <x-primary-button type="submit" onclick="return confirm('Merge these two leads? This cannot be undone from the UI.')">Merge Leads</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
