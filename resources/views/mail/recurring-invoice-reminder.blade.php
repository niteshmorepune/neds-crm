<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Invoice Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: #4f46e5; padding: 28px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; color: #374151; font-size: 15px; line-height: 1.6; }
        .info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .info-row:last-child { margin-bottom: 0; }
        .label { color: #6b7280; font-size: 13px; }
        .value { font-weight: 600; color: #111827; }
        .badge { display: inline-block; background: #eef2ff; color: #4338ca; border-radius: 12px; padding: 3px 10px; font-size: 13px; font-weight: 600; }
        .footer { padding: 20px 32px; border-top: 1px solid #f3f4f6; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ $companyName }}</h1>
    </div>
    <div class="body">
        <p>Dear {{ $recurring->customer?->company_name }},</p>
        <p>
            This is a friendly reminder that your invoice for <strong>{{ $service }}</strong>
            is due in <span class="badge">{{ $daysUntil }} {{ Str::plural('day', $daysUntil) }}</span>
            on <strong>{{ $billingDate }}</strong>.
        </p>

        <div class="info-box">
            <div class="info-row">
                <span class="label">Service</span>
                <span class="value">{{ $service }}</span>
            </div>
            <div class="info-row">
                <span class="label">Billing date</span>
                <span class="value">{{ $billingDate }}</span>
            </div>
            <div class="info-row">
                <span class="label">Estimated amount</span>
                <span class="value">{{ $subtotal }}</span>
            </div>
            <div class="info-row">
                <span class="label">Billing frequency</span>
                <span class="value">{{ $recurring->frequency->label() }}</span>
            </div>
        </div>

        <p>
            Please ensure your payment details are up to date. Once the invoice is generated,
            you will receive it at this email address. You can also view all your invoices
            in the client portal.
        </p>
        <p>If you have any questions, please contact us.</p>
        <p>Thank you,<br><strong>{{ $companyName }}</strong></p>
    </div>
    <div class="footer">
        You received this reminder because you have an active recurring billing agreement with {{ $companyName }}.
    </div>
</div>
</body>
</html>
