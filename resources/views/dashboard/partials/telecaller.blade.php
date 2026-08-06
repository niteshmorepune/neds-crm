<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">New leads to call</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['new_leads']) }}</p>
        <div class="mt-3">
            <a href="{{ route('leads.index', ['status' => \App\Enums\LeadStatus::New->value]) }}" class="text-sm text-indigo-600 hover:underline">View leads →</a>
        </div>
    </div>
    <div class="rounded-lg bg-white p-5 shadow-sm">
        <p class="text-sm text-gray-500">Calls made today</p>
        <p class="mt-2 text-3xl font-semibold text-green-600">{{ number_format($stats['calls_today']) }}</p>
        <div class="mt-3">
            <a href="{{ route('calls.index', ['date' => now()->toDateString()]) }}" class="text-sm text-indigo-600 hover:underline">View calls →</a>
        </div>
    </div>
    <a href="{{ route('calls.index', ['pending_followup' => 1]) }}" class="block rounded-lg bg-white p-5 shadow-sm hover:shadow-md">
        <p class="text-sm text-gray-500">Follow-ups due</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ number_format($stats['followups_due']) }}</p>
    </a>
</div>
