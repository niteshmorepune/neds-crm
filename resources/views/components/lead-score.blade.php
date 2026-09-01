@props(['lead'])

@php $scoreBand = \App\Models\Lead::scoreBandFor($lead->ai_score); @endphp
@if (! is_null($lead->ai_score))
    <span @class([
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
        'bg-green-100 text-green-800' => $scoreBand === 'hot',
        'bg-yellow-100 text-yellow-800' => $scoreBand === 'warm',
        'bg-gray-100 text-gray-600' => $scoreBand === 'cold',
    ]) @if ($lead->ai_score_reason) title="{{ $lead->ai_score_reason }}" @endif>
        <span class="font-semibold">AI {{ $lead->ai_score }}</span>
    </span>
    @if ($lead->isHot())
        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
            🔥 Hot
        </span>
    @endif
@endif
