@php($anyStatCard = collect(['new_leads', 'calls_today', 'followups_due'])->contains(fn ($k) => in_array($k, $visibleWidgets)))

@if ($anyStatCard)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @if (in_array('new_leads', $visibleWidgets))
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">New leads to call</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['new_leads']) }}</p>
                <div class="mt-3">
                    <a href="{{ route('leads.index', ['status' => \App\Enums\LeadStatus::New->value]) }}" class="text-sm text-indigo-600 hover:underline">View leads →</a>
                </div>
            </div>
        @endif
        @if (in_array('calls_today', $visibleWidgets))
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Calls made today</p>
                <p class="mt-2 text-3xl font-semibold text-green-600">{{ number_format($stats['calls_today']) }}</p>
                <div class="mt-3">
                    <a href="{{ route('calls.index', ['date' => now()->toDateString()]) }}" class="text-sm text-indigo-600 hover:underline">View calls →</a>
                </div>
            </div>
        @endif
        @if (in_array('followups_due', $visibleWidgets))
            <a href="{{ route('calls.index', ['pending_followup' => 1]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
                <p class="text-sm text-gray-500">Follow-ups due</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['followups_due']) }}</p>
            </a>
        @endif
    </div>
@endif

@if (in_array('target_progress', $visibleWidgets) && $targetProgress)
    <div class="max-w-sm rounded-lg bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-medium text-gray-500">Your target this month</p>
        <x-target-progress-bar :metric="$targetProgress['metric']" :target="$targetProgress['target']" :actual="$targetProgress['actual']" :pct="$targetProgress['pct']" />
    </div>
@endif
