@props(['value', 'suffix' => ''])

@if ($value !== null && $value !== 0)
    <span @class([
        'ml-1 text-xs font-medium',
        'text-green-600' => $value > 0,
        'text-red-600' => $value < 0,
    ])>({{ $value > 0 ? '+' : '' }}{{ $value }}{{ $suffix }})</span>
@endif
