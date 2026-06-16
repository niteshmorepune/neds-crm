<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $invoice->customer->company_name }},</p>
    <p>Please find your invoice <strong>{{ $invoice->invoice_number }}</strong> for <strong>{{ $total }}</strong>,
       issued {{ $invoice->issue_date->format('d M Y') }}@if ($invoice->due_date), due {{ $invoice->due_date->format('d M Y') }}@endif.</p>
    <p>Amount in words: {{ $invoice->amountInWords() }}</p>

    @if ($invoice->milestones->isNotEmpty())
        <p>This invoice covers the following installment(s):</p>
        <table cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 480px;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid #d1d5db;">
                    <th>Installment</th>
                    <th>%</th>
                    <th>Amount</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->milestones as $milestone)
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td>{{ $milestone->title }}</td>
                        <td>{{ rtrim(rtrim($milestone->percentage, '0'), '.') }}%</td>
                        <td>{{ \App\Support\Money::format($milestone->amount) }}</td>
                        <td>{{ $milestone->due_date?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
