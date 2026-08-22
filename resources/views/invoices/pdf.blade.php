<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .box { border: 1px solid #333333; }
        .grid th, .grid td { border: 1px solid #333333; padding: 5px 6px; }
        .grid th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; color: #374151; }
        .grid tfoot td { font-weight: bold; background: #f9fafb; }
        .no-border { border: none; }
        .pad { padding: 8px 10px; }
        .title { font-size: 15px; font-weight: bold; }
        .brand { font-size: 16px; font-weight: bold; color: #4f46e5; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; color: #4f46e5; font-size: 10px; }
        .totals-label { color: #6b7280; }
        .grand { font-size: 13px; font-weight: bold; border-top: 1px solid #9ca3af; }
    </style>
</head>
<body>
    @php($company = config('company'))
    @php($logo = \App\Support\CompanyLogo::dataUri())
    @php($isNoTax = $invoice->customer?->isOverseas() || $invoice->is_gst_exempt)

    <table class="no-border" style="margin-bottom:4px;">
        <tr>
            <td class="no-border center title">{{ $isNoTax ? 'INVOICE' : 'TAX INVOICE' }}</td>
        </tr>
        <tr>
            <td class="no-border right muted" style="font-size:10px;">(Original Copy)</td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td class="pad no-border" style="width:62%;vertical-align:top;border-right:1px solid #333333;">
                <table class="no-border">
                    <tr>
                        @if ($logo)
                            <td class="no-border" style="width:70px;vertical-align:top;"><img src="{{ $logo }}" width="60"></td>
                        @endif
                        <td class="no-border" style="vertical-align:top;">
                            <div class="brand">{{ $company['name'] }}</div>
                            <div class="muted">{{ $company['address'] }}</div>
                            @if ($company['phone'])<div class="muted">Contact: {{ $company['phone'] }}</div>@endif
                            <div class="muted">{{ $company['email'] }}</div>
                            <div class="muted">GSTIN: {{ $company['gstin'] }} · State: {{ $company['state'] }} ({{ $company['state_code'] }})</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="pad no-border" style="width:38%;vertical-align:top;">
                <table class="no-border">
                    <tr><td class="no-border muted" style="width:50%;">Invoice No.</td><td class="no-border muted">Date</td></tr>
                    <tr><td class="no-border" style="font-weight:bold;">{{ $invoice->invoice_number }}</td><td class="no-border" style="font-weight:bold;">{{ $invoice->issue_date->format('d-m-Y') }}</td></tr>
                    @if ($invoice->due_date)
                        <tr><td class="no-border muted" colspan="2" style="padding-top:6px;">Due</td></tr>
                        <tr><td class="no-border" colspan="2">{{ $invoice->due_date->format('d-m-Y') }}</td></tr>
                    @endif
                    <tr><td class="no-border" colspan="2" style="padding-top:6px;"><span class="badge">{{ $invoice->status->label() }}</span></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="box" style="border-top:none;">
        <tr>
            <td class="pad no-border" style="width:62%;vertical-align:top;border-right:1px solid #333333;">
                <div style="font-weight:bold;margin-bottom:4px;">Bill To:</div>
                @if ($invoice->customer)
                    <div style="font-weight:bold;">{{ $invoice->customer->company_name }}</div>
                    @if ($invoice->customer->address_line1){{ $invoice->customer->address_line1 }}<br>@endif
                    @if ($invoice->customer->city){{ $invoice->customer->city }}@if($invoice->customer->state || $invoice->customer->country), @endif@endif
                    @if($invoice->customer->isOverseas())
                        {{ $invoice->customer->country }}<br>
                    @else
                        {{ $invoice->customer->state }}<br>
                        GSTIN: {{ $invoice->customer->gstin ?? 'Unregistered' }}
                    @endif
                @else
                    Client removed
                @endif
            </td>
            <td class="pad no-border" style="width:38%;vertical-align:top;">
                @if($invoice->customer?->isOverseas())
                    <div style="font-weight:bold;">Export of Services</div>
                    <span style="color:#059669;">Zero-Rated Supply (GST not applicable)</span>
                @elseif($invoice->is_gst_exempt)
                    <div style="font-weight:bold;">Non-GST Invoice</div>
                    <span style="color:#059669;">GST not charged on this invoice</span>
                @else
                    <div style="font-weight:bold;">Place of supply</div>
                    {{ $invoice->customer?->state ?? '—' }} ({{ $invoice->place_of_supply_state_code }})<br>
                    {{ $invoice->is_intra_state ? 'Intra-state — CGST + SGST' : 'Inter-state — IGST' }}
                @endif
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-top:10px;">
        <thead>
            <tr>
                <th style="width:4%;">S.No.</th><th>Particulars</th><th style="width:10%;">HSN/SAC</th>
                <th class="right" style="width:8%;">Qty</th><th class="right" style="width:12%;">Unit Price</th>
                <th class="right" style="width:8%;">GST%</th><th class="right" style="width:14%;">Amount</th>
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
        <tfoot>
            <tr>
                <td colspan="3">TOTAL</td>
                <td class="right">{{ rtrim(rtrim($invoice->items->sum('quantity'), '0'), '.') }}</td>
                <td colspan="2"></td>
                <td class="right">{{ \App\Support\Money::format($invoice->subtotal) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="box" style="border-top:none;">
        <tr>
            <td class="pad no-border" style="width:55%;vertical-align:top;border-right:1px solid #333333;">
                <div class="muted" style="margin-bottom:4px;">Amount in Words:</div>
                <strong>{{ $invoice->amountInWords() }}</strong>
            </td>
            <td class="pad no-border" style="width:45%;vertical-align:top;">
                <table class="no-border">
                    <tr><td class="no-border totals-label">Subtotal</td><td class="no-border right">{{ \App\Support\Money::format($invoice->subtotal) }}</td></tr>
                    <tr><td class="no-border totals-label">Discount</td><td class="no-border right">− {{ \App\Support\Money::format($invoice->discount) }}</td></tr>
                    <tr><td class="no-border totals-label">Taxable value</td><td class="no-border right">{{ \App\Support\Money::format($invoice->taxable_total) }}</td></tr>
                    @if($invoice->customer?->isOverseas())
                        <tr><td class="no-border totals-label">GST</td><td class="no-border right" style="color:#059669;">Nil (Export / Zero-Rated)</td></tr>
                    @elseif($invoice->is_gst_exempt)
                        <tr><td class="no-border totals-label">GST</td><td class="no-border right" style="color:#059669;">Not charged</td></tr>
                    @elseif ($invoice->is_intra_state)
                        <tr><td class="no-border totals-label">CGST</td><td class="no-border right">{{ \App\Support\Money::format($invoice->cgst_total) }}</td></tr>
                        <tr><td class="no-border totals-label">SGST</td><td class="no-border right">{{ \App\Support\Money::format($invoice->sgst_total) }}</td></tr>
                    @else
                        <tr><td class="no-border totals-label">IGST</td><td class="no-border right">{{ \App\Support\Money::format($invoice->igst_total) }}</td></tr>
                    @endif
                    <tr><td class="no-border totals-label">Round off</td><td class="no-border right">{{ \App\Support\Money::format($invoice->round_off) }}</td></tr>
                    <tr><td class="no-border grand">Total</td><td class="no-border right grand">{{ \App\Support\Money::format($invoice->total) }}</td></tr>
                    <tr><td class="no-border totals-label">Amount paid</td><td class="no-border right">{{ \App\Support\Money::format($invoice->amount_paid) }}</td></tr>
                    @if ($invoice->tdsTotal() > 0)
                        <tr><td class="no-border totals-label">TDS deducted</td><td class="no-border right">− {{ \App\Support\Money::format($invoice->tdsTotal()) }}</td></tr>
                        <tr><td class="no-border grand">Net payable</td><td class="no-border right grand">{{ \App\Support\Money::format($invoice->balance()) }}</td></tr>
                    @else
                        <tr><td class="no-border totals-label">Balance due</td><td class="no-border right">{{ \App\Support\Money::format($invoice->balance()) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @unless ($isNoTax)
        <table class="grid" style="margin-top:10px;">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align:middle;">HSN/SAC</th>
                    <th rowspan="2" class="right" style="vertical-align:middle;">Taxable Amount</th>
                    @if ($invoice->is_intra_state)
                        <th colspan="2" class="center">CGST</th>
                        <th colspan="2" class="center">SGST</th>
                    @else
                        <th colspan="2" class="center">IGST</th>
                    @endif
                    <th rowspan="2" class="right" style="vertical-align:middle;">Total Tax Amount</th>
                </tr>
                <tr>
                    @if ($invoice->is_intra_state)
                        <th class="right">Rate</th><th class="right">Amount</th>
                        <th class="right">Rate</th><th class="right">Amount</th>
                    @else
                        <th class="right">Rate</th><th class="right">Amount</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->hsnSummary() as $row)
                    <tr>
                        <td>{{ $row['sac_code'] }}</td>
                        <td class="right">{{ \App\Support\Money::format($row['taxable']) }}</td>
                        @if ($invoice->is_intra_state)
                            <td class="right">{{ rtrim(rtrim($row['gst_rate'] / 2, '0'), '.') }}%</td>
                            <td class="right">{{ \App\Support\Money::format($row['cgst']) }}</td>
                            <td class="right">{{ rtrim(rtrim($row['gst_rate'] / 2, '0'), '.') }}%</td>
                            <td class="right">{{ \App\Support\Money::format($row['sgst']) }}</td>
                            <td class="right">{{ \App\Support\Money::format($row['cgst'] + $row['sgst']) }}</td>
                        @else
                            <td class="right">{{ rtrim(rtrim($row['gst_rate'], '0'), '.') }}%</td>
                            <td class="right">{{ \App\Support\Money::format($row['igst']) }}</td>
                            <td class="right">{{ \App\Support\Money::format($row['igst']) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="right">{{ \App\Support\Money::format($invoice->taxable_total) }}</td>
                    @if ($invoice->is_intra_state)
                        <td></td><td class="right">{{ \App\Support\Money::format($invoice->cgst_total) }}</td>
                        <td></td><td class="right">{{ \App\Support\Money::format($invoice->sgst_total) }}</td>
                        <td class="right">{{ \App\Support\Money::format($invoice->cgst_total + $invoice->sgst_total) }}</td>
                    @else
                        <td></td><td class="right">{{ \App\Support\Money::format($invoice->igst_total) }}</td>
                        <td class="right">{{ \App\Support\Money::format($invoice->igst_total) }}</td>
                    @endif
                </tr>
            </tfoot>
        </table>
    @endunless

    <table class="box" style="border-top:none;">
        <tr>
            <td class="pad no-border" style="width:60%;vertical-align:top;border-right:1px solid #333333;">
                <div style="font-weight:bold;margin-bottom:4px;">Terms / Declaration</div>
                <div class="muted" style="margin-bottom:10px;">
                    @if($isNoTax)
                        This is a computer-generated invoice. Not require sign.
                    @else
                        This is a computer-generated tax invoice. Not require sign.
                    @endif
                </div>
                @if ($company['account_number'] || $company['upi_id'])
                    <div style="font-weight:bold;margin-bottom:3px;">Bank Details</div>
                    @if ($company['bank_name'])<div>Bank Name: {{ $company['bank_name'] }}</div>@endif
                    @if ($company['account_name'])<div>Name: {{ $company['account_name'] }}</div>@endif
                    @if ($company['account_number'])<div>Account No: <strong>{{ $company['account_number'] }}</strong></div>@endif
                    @if ($company['ifsc_code'])<div>Branch &amp; IFSC: <strong>{{ $company['ifsc_code'] }}</strong></div>@endif
                    @if ($company['upi_id'])<div>UPI: <strong>{{ $company['upi_id'] }}</strong></div>@endif
                @endif
            </td>
            <td class="pad no-border center" style="width:40%;vertical-align:top;">
                @if ($company['upi_id'] && $invoice->balance() > 0)
                    <div class="muted" style="margin-bottom:3px;">Scan to Pay</div>
                    <img src="{{ \App\Support\UpiQrCode::dataUri(
                        $company['upi_id'],
                        $company['name'],
                        \App\Support\Money::toRupees($invoice->balance()),
                        $invoice->invoice_number ?? 'Invoice',
                    ) }}" width="90" height="90">
                @endif
                <div style="margin-top:26px;">
                    <div>For {{ $company['name'] }}</div>
                    <div style="height:34px;"></div>
                    <div style="border-top:1px solid #9ca3af;padding-top:3px;">
                        @if ($company['signatory_name']){{ $company['signatory_name'] }}<br>@endif
                        <span class="muted">Authorized Signatory</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin-top:16px;font-size:10px;">
        @if($invoice->customer?->isOverseas())
            This is a computer-generated invoice for export of services. Supply is zero-rated under the IGST Act, 2017. Subject to Maharashtra jurisdiction.
        @elseif($invoice->is_gst_exempt)
            This is a computer-generated invoice. No GST has been charged on this invoice. Subject to Maharashtra jurisdiction.
        @else
            This is a computer-generated tax invoice. Subject to Maharashtra jurisdiction.
        @endif
    </p>
</body>
</html>
