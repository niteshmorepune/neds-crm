<x-app-layout>
    <x-slot name="header">Best Employee of the Quarter</x-slot>

    <div class="max-w-6xl mx-auto space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="text-sm text-gray-500">
            Each financial-year quarter, AI reviews performance numbers already tracked elsewhere in the
            CRM and suggests a winner per department plus one company-wide "Best Employee of the Quarter."
            Nothing is announced until an Admin or Manager reviews and approves it — certificate + citation
            only, no reward amount tracked here.
        </p>

        @if ($isManager)
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Generate / regenerate for a quarter</h3>
                <p class="mt-1 text-xs text-gray-500">
                    Runs automatically on quarter close. Use this to (re)run it manually — an already-approved
                    award for that quarter is never touched.
                </p>
                <form method="POST" action="{{ route('quarterly-awards.regenerate') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Financial year</label>
                        <input type="text" name="financial_year" placeholder="2026-27" pattern="\d{4}-\d{2}" required
                               class="mt-1 block w-28 rounded-md border-gray-300 text-sm shadow-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Quarter</label>
                        <select name="quarter" required class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="1">Q1 (Apr-Jun)</option>
                            <option value="2">Q2 (Jul-Sep)</option>
                            <option value="3">Q3 (Oct-Dec)</option>
                            <option value="4">Q4 (Jan-Mar)</option>
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        Generate
                    </button>
                </form>
                @error('financial_year')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('quarter')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-900">Pending review</h3>
                <livewire:quarterly-award-review :pending-awards="$awards->where('status', App\Enums\AwardStatus::Pending)->values()" />
            </div>
        @endif

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Award</th>
                        <th class="px-4 py-3">Quarter</th>
                        <th class="px-4 py-3">Winner</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($awards as $award)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $award->title() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $award->periodLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $award->user->name }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700' => $award->status === App\Enums\AwardStatus::Approved,
                                    'bg-amber-50 text-amber-700' => $award->status === App\Enums\AwardStatus::Pending,
                                    'bg-gray-100 text-gray-600' => $award->status === App\Enums\AwardStatus::Rejected,
                                ])>{{ $award->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @can('downloadCertificate', $award)
                                    <a href="{{ route('quarterly-awards.certificate', $award) }}" class="text-indigo-600 hover:text-indigo-500">Certificate</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                {{ $isManager ? 'No awards generated yet.' : "You haven't been recognized yet — check back next quarter." }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
