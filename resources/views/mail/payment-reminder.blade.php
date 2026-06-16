<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $invoice->customer?->company_name ?? 'Customer' }},</p>
    <p>This is a reminder that invoice <strong>{{ $invoice->invoice_number }}</strong> has an outstanding balance of
       <strong>{{ $balance }}</strong>@if ($invoice->due_date), due {{ $invoice->due_date->format('d M Y') }}@endif.</p>
    <p>Please arrange payment at your earliest convenience.</p>
    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
