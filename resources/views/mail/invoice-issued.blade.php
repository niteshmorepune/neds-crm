<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $invoice->customer->company_name }},</p>
    <p>Please find your invoice <strong>{{ $invoice->invoice_number }}</strong> for <strong>{{ $total }}</strong>,
       issued {{ $invoice->issue_date->format('d M Y') }}@if ($invoice->due_date), due {{ $invoice->due_date->format('d M Y') }}@endif.</p>
    <p>Amount in words: {{ $invoice->amountInWords() }}</p>
    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
