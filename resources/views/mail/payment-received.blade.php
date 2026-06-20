<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $invoice->customer?->company_name ?? 'Customer' }},</p>
    <p>We have received your payment of <strong>{{ $amountPaid }}</strong> on {{ $payment->paid_on->format('d M Y') }}
       against invoice <strong>{{ $invoice->invoice_number }}</strong>.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 420px; margin: 12px 0;">
        <tr style="background: #f9fafb;">
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Invoice number</td>
            <td style="border: 1px solid #e5e7eb;">{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Invoice total</td>
            <td style="border: 1px solid #e5e7eb;">{{ \App\Support\Money::format($invoice->total) }}</td>
        </tr>
        <tr style="background: #f9fafb;">
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Amount received</td>
            <td style="border: 1px solid #e5e7eb;">{{ $amountPaid }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Payment mode</td>
            <td style="border: 1px solid #e5e7eb;">{{ $payment->mode->label() }}</td>
        </tr>
        @if ($payment->reference)
        <tr style="background: #f9fafb;">
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Reference</td>
            <td style="border: 1px solid #e5e7eb;">{{ $payment->reference }}</td>
        </tr>
        @endif
        <tr>
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Balance due</td>
            <td style="border: 1px solid #e5e7eb;">{{ $balance }}</td>
        </tr>
    </table>

    @if ($invoice->balance() <= 0)
        <p>Your account is now fully settled for this invoice. Thank you!</p>
    @else
        <p>The remaining balance of <strong>{{ $balance }}</strong> is due as per the invoice terms.</p>
    @endif

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
