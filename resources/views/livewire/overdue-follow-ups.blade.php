<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900">
        Overdue follow-ups
        @php($total = $leads->count() + $deals->count() + $callFollowUps->count())
        @if ($total > 0)
            <span class="ml-1 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $total }}</span>
        @endif
    </h2>

    @if ($leads->isEmpty() && $deals->isEmpty() && $callFollowUps->isEmpty())
        <p class="mt-3 text-sm text-gray-400">Nothing overdue. 🎉</p>
    @else
        <ul class="mt-3 divide-y divide-gray-100 text-sm">
            @foreach ($leads as $lead)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('leads.show', $lead) }}" class="text-indigo-600 hover:underline">
                        Lead: {{ $lead->name }}{{ $lead->company ? ' — '.$lead->company : '' }}
                    </a>
                    <span class="text-xs text-red-600">due {{ $lead->next_follow_up_at->timezone(config('app.display_timezone'))->format('d M') }}</span>
                </li>
            @endforeach
            @foreach ($deals as $deal)
                <li class="flex items-center justify-between py-2">
                    <a href="{{ route('deals.show', $deal) }}" class="text-indigo-600 hover:underline">
                        Deal: {{ $deal->title }} — {{ $deal->customer?->company_name ?? 'Client removed' }}
                    </a>
                    <span class="text-xs text-red-600">due {{ $deal->next_follow_up_at->timezone(config('app.display_timezone'))->format('d M') }}</span>
                </li>
            @endforeach
            @foreach ($callFollowUps as $call)
                <li class="flex items-start justify-between py-2 gap-4">
                    <div>
                        @if ($call->callable instanceof \App\Models\Customer)
                            <a href="{{ route('clients.show', $call->callable->id) }}" class="text-indigo-600 hover:underline">Call: {{ $call->callable->company_name }}</a>
                        @elseif ($call->callable instanceof \App\Models\Lead)
                            <a href="{{ route('leads.show', $call->callable->id) }}" class="text-indigo-600 hover:underline">Call: {{ $call->callable->name }}</a>
                        @else
                            <a href="{{ route('calls.index') }}" class="text-indigo-600 hover:underline">Call follow-up</a>
                        @endif
                        @if ($call->next_action)
                            <p class="text-xs text-gray-500 mt-0.5">{{ $call->next_action }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 text-xs text-red-600">
                        due {{ $call->follow_up_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
