<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $quotation->customer?->company_name ?? 'Customer' }},</p>
    <p>Please find your quotation <strong>{{ $quotation->number }}</strong> for <strong>{{ $total }}</strong>.</p>
    <table style="width:100%; border-collapse:collapse; margin: 16px 0; font-size: 14px;">
        <thead>
            <tr style="background:#f3f4f6; text-align:left;">
                <th style="padding:8px; border-bottom:1px solid #e5e7eb;">Description</th>
                <th style="padding:8px; border-bottom:1px solid #e5e7eb; text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $item)
            <tr>
                <td style="padding:8px; border-bottom:1px solid #f3f4f6;">{{ $item->description }}</td>
                <td style="padding:8px; border-bottom:1px solid #f3f4f6; text-align:right;">{{ \App\Support\Money::format($item->amount) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td style="padding:8px; font-weight:bold;">Total</td>
                <td style="padding:8px; font-weight:bold; text-align:right;">{{ $total }}</td>
            </tr>
        </tfoot>
    </table>
    @if ($quotation->terms)
        <p style="font-size:13px; color:#6b7280;"><strong>Terms:</strong> {{ $quotation->terms }}</p>
    @endif
    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
