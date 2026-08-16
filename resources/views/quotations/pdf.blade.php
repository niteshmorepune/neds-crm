<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 0; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { font-size: 18px; font-weight: bold; color: #4f46e5; }
        table { width: 100%; border-collapse: collapse; }
        .parties td { vertical-align: top; width: 50%; padding-top: 8px; }
        .items th { background: #f3f4f6; text-align: left; padding: 6px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        .items td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        .totals td { padding: 3px 6px; }
        .label-cell { width: 65%; }
        .grand { border-top: 1px solid #9ca3af; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; color: #4f46e5; font-size: 11px; }
    </style>
</head>
<body>
    @php($company = config('company'))

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">{{ $company['name'] }}</div>
                    <div class="muted">{{ $company['address'] }}</div>
                    <div class="muted">GSTIN: {{ $company['gstin'] }} · State: {{ $company['state'] }} ({{ $company['state_code'] }})</div>
                    <div class="muted">{{ $company['email'] }}</div>
                </td>
                <td class="right">
                    <div style="font-size:16px;font-weight:bold;">QUOTATION</div>
                    <div>{{ $quotation->number }}</div>
                    @if ($quotation->validity_date)<div class="muted">Valid until: {{ $quotation->validity_date->format('d M Y') }}</div>@endif
                    <div><span class="badge">{{ $quotation->status->label() }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    <table class="parties">
        <tr>
            <td>
                <strong>Prepared For</strong><br>
                @if ($quotation->customer)
                    {{ $quotation->customer->company_name }}<br>
                    @if ($quotation->customer->address_line1){{ $quotation->customer->address_line1 }}<br>@endif
                    @if ($quotation->customer->city){{ $quotation->customer->city }}@if($quotation->customer->state || $quotation->customer->country), @endif@endif
                    @if($quotation->customer->isOverseas())
                        {{ $quotation->customer->country }}<br>
                    @else
                        {{ $quotation->customer->state }}<br>
                        GSTIN: {{ $quotation->customer->gstin ?? 'Unregistered' }}
                    @endif
                @else
                    Client removed
                @endif
            </td>
            <td class="right">
                @if($quotation->customer?->isOverseas())
                    <strong>Export of Services</strong><br>
                    <span style="color:#059669;">Zero-Rated Supply (GST not applicable)</span>
                @elseif($quotation->is_gst_exempt)
                    <strong>Non-GST Quotation</strong><br>
                    <span style="color:#059669;">GST not charged</span>
                @else
                    <strong>Place of supply</strong><br>
                    {{ $quotation->customer?->state ?? '—' }} ({{ $quotation->place_of_supply_state_code }})<br>
                    {{ $quotation->is_intra_state ? 'Intra-state — CGST + SGST' : 'Inter-state — IGST' }}
                @endif
            </td>
        </tr>
    </table>

    @if ($quotation->scope_of_work)
        <div style="margin-top:14px;">
            <strong>Scope of Work</strong>
            <p class="muted" style="white-space:pre-line;">{{ $quotation->scope_of_work }}</p>
        </div>
    @endif

    <br>
    <table class="items">
        <thead>
            <tr>
                <th>#</th><th>Description</th><th>SAC/HSN</th>
                <th class="right">Qty</th><th class="right">Rate</th><th class="right">GST%</th><th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $n => $item)
                <tr>
                    <td>{{ $n + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->sac_code ?? '—' }}</td>
                    <td class="right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                    <td class="right">{{ \App\Support\Money::format($item->rate) }}</td>
                    <td class="right">{{ rtrim(rtrim($item->gst_rate, '0'), '.') }}%</td>
                    <td class="right">{{ \App\Support\Money::format($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <table class="totals">
        <tr><td class="label-cell right muted">Subtotal</td><td class="right">{{ \App\Support\Money::format($quotation->subtotal) }}</td></tr>
        <tr><td class="label-cell right muted">Discount</td><td class="right">− {{ \App\Support\Money::format($quotation->discount) }}</td></tr>
        <tr><td class="label-cell right muted">Taxable value</td><td class="right">{{ \App\Support\Money::format($quotation->taxable_total) }}</td></tr>
        @if($quotation->customer?->isOverseas())
            <tr><td class="label-cell right muted">GST</td><td class="right" style="color:#059669;">Nil (Export / Zero-Rated)</td></tr>
        @elseif($quotation->is_gst_exempt)
            <tr><td class="label-cell right muted">GST</td><td class="right" style="color:#059669;">Not charged</td></tr>
        @elseif ($quotation->is_intra_state)
            <tr><td class="label-cell right muted">CGST</td><td class="right">{{ \App\Support\Money::format($quotation->cgst_total) }}</td></tr>
            <tr><td class="label-cell right muted">SGST</td><td class="right">{{ \App\Support\Money::format($quotation->sgst_total) }}</td></tr>
        @else
            <tr><td class="label-cell right muted">IGST</td><td class="right">{{ \App\Support\Money::format($quotation->igst_total) }}</td></tr>
        @endif
        <tr><td class="label-cell right muted">Round off</td><td class="right">{{ \App\Support\Money::format($quotation->round_off) }}</td></tr>
        <tr><td class="label-cell right grand">Total</td><td class="right grand">{{ \App\Support\Money::format($quotation->total) }}</td></tr>
    </table>

    <p style="margin-top:12px;"><strong>Amount in words:</strong> {{ $quotation->amountInWords() }}</p>

    @if ($quotation->terms)
        <div style="margin-top:14px;">
            <strong>Terms</strong>
            <p class="muted" style="white-space:pre-line;">{{ $quotation->terms }}</p>
        </div>
    @endif

    <p class="muted" style="margin-top:24px;font-size:11px;">
        This is a computer-generated quotation{{ $quotation->validity_date ? ', valid until '.$quotation->validity_date->format('d M Y') : '' }}. Subject to Maharashtra jurisdiction.
    </p>
</body>
</html>
