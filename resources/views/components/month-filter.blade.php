@props(['month'])

{{--
    Global Dashboard Filters (Tier 3 #15) + Saved Manager Views (Tier 3 #16).
    Saved Manager Views was scoped down to "recent-months quick-jump chips,
    no new table" — with only one filterable dimension (month), a native
    picker is already about as fast as recalling a saved preset, so the
    chips below ARE the "saved views" rather than a separate save/load
    system. Both pieces share this one component since they're the same UI.
--}}
<div class="flex flex-wrap items-center gap-2 text-xs">
    <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-1.5">
        <input type="month" name="month" value="{{ $month }}" class="rounded-md border-gray-300 text-xs shadow-sm" />
        <button class="rounded-md bg-gray-800 px-2.5 py-1 font-medium text-white hover:bg-gray-700">Go</button>
    </form>

    <div class="flex items-center gap-1">
        @for ($i = 0; $i < 4; $i++)
            @php($chipMonth = now()->subMonthsNoOverflow($i))
            @php($chipValue = $chipMonth->format('Y-m'))
            <a href="{{ route('dashboard', ['month' => $chipValue]) }}" @class([
                'rounded-full px-2.5 py-1 font-medium',
                'bg-indigo-600 text-white' => $chipValue === $month,
                'bg-gray-100 text-gray-600 hover:bg-gray-200' => $chipValue !== $month,
            ])>{{ $i === 0 ? 'This month' : $chipMonth->format('M Y') }}</a>
        @endfor
    </div>
</div>
