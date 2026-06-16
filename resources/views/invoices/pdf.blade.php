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
                    <div style="font-size:16px;font-weight:bold;">TAX INVOICE</div>
                    <div>{{ $invoice->invoice_number }}</div>
                    <div class="muted">Issued: {{ $invoice->issue_date->format('d M Y') }}</div>
                    @if ($invoice->due_date)<div class="muted">Due: {{ $invoice->due_date->format('d M Y') }}</div>@endif
                    <div><span class="badge">{{ $invoice->status->label() }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    <table class="parties">
        <tr>
            <td>
                <strong>Bill To</strong><br>
                @if ($invoice->customer)
                    {{ $invoice->customer->company_name }}<br>
                    @if ($invoice->customer->address_line1){{ $invoice->customer->address_line1 }}<br>@endif
                    @if ($invoice->customer->city){{ $invoice->customer->city }}, @endif{{ $invoice->customer->state }}<br>
                    GSTIN: {{ $invoice->customer->gstin ?? 'Unregistered' }}
                @else
                    Client removed
                @endif
            </td>
            <td class="right">
                <strong>Place of supply</strong><br>
                {{ $invoice->customer?->state ?? '—' }} ({{ $invoice->place_of_supply_state_code }})<br>
                {{ $invoice->is_intra_state ? 'Intra-state — CGST + SGST' : 'Inter-state — IGST' }}
            </td>
        </tr>
    </table>

    <br>
    <table class="items">
        <thead>
            <tr>
                <th>#</th><th>Description</th><th>SAC/HSN</th>
                <th class="right">Qty</th><th class="right">Rate</th><th class="right">GST%</th><th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $n => $item)
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
        <tr><td class="label-cell right muted">Subtotal</td><td class="right">{{ \App\Support\Money::format($invoice->subtotal) }}</td></tr>
        <tr><td class="label-cell right muted">Discount</td><td class="right">− {{ \App\Support\Money::format($invoice->discount) }}</td></tr>
        <tr><td class="label-cell right muted">Taxable value</td><td class="right">{{ \App\Support\Money::format($invoice->taxable_total) }}</td></tr>
        @if ($invoice->is_intra_state)
            <tr><td class="label-cell right muted">CGST</td><td class="right">{{ \App\Support\Money::format($invoice->cgst_total) }}</td></tr>
            <tr><td class="label-cell right muted">SGST</td><td class="right">{{ \App\Support\Money::format($invoice->sgst_total) }}</td></tr>
        @else
            <tr><td class="label-cell right muted">IGST</td><td class="right">{{ \App\Support\Money::format($invoice->igst_total) }}</td></tr>
        @endif
        <tr><td class="label-cell right muted">Round off</td><td class="right">{{ \App\Support\Money::format($invoice->round_off) }}</td></tr>
        <tr><td class="label-cell right grand">Total</td><td class="right grand">{{ \App\Support\Money::format($invoice->total) }}</td></tr>
        <tr><td class="label-cell right muted">Amount paid</td><td class="right">{{ \App\Support\Money::format($invoice->amount_paid) }}</td></tr>
        <tr><td class="label-cell right muted">Balance due</td><td class="right">{{ \App\Support\Money::format($invoice->balance()) }}</td></tr>
    </table>

    <p style="margin-top:12px;"><strong>Amount in words:</strong> {{ $invoice->amountInWords() }}</p>

    <p class="muted" style="margin-top:24px;font-size:11px;">
        This is a computer-generated tax invoice. Subject to Maharashtra jurisdiction.
    </p>
</body>
</html>
