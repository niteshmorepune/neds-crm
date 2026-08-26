<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $purchase->payer_name ?: 'there' }},</p>
    <p>Quick update — our team has started working on your <strong>{{ $tierLabel }}</strong>.
       We're reviewing your listing and putting together the gaps and opportunities we'll
       walk you through.</p>

    <p>We'll let you know here (and on WhatsApp) the moment it's ready.</p>

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
