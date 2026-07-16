@props(['kpis'])

@php
    $kpiCards = [
        ['label' => 'Open pipeline', 'value' => \App\Support\Money::format($kpis['open_pipeline_value'])],
        ['label' => 'Weighted forecast', 'value' => \App\Support\Money::format($kpis['weighted_forecast'])],
        ['label' => 'Won this month', 'value' => \App\Support\Money::format($kpis['won_this_month_value'])],
        ['label' => 'Won this FY', 'value' => \App\Support\Money::format($kpis['won_this_fy_value'])],
        ['label' => 'Win rate', 'value' => $kpis['win_rate'] !== null ? $kpis['win_rate'].'%' : '—'],
        ['label' => 'Avg deal size', 'value' => \App\Support\Money::format($kpis['avg_deal_size'])],
        ['label' => 'Avg sales cycle', 'value' => $kpis['avg_sales_cycle_days'] !== null ? $kpis['avg_sales_cycle_days'].' days' : '—'],
    ];
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7']) }}>
    @foreach ($kpiCards as $card)
        <div class="rounded-lg bg-white p-4 shadow-sm">
            <p class="text-xs text-gray-500">{{ $card['label'] }}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>
