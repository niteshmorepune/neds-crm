<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $purchase->payer_name ?: 'there' }},</p>
    <p>Great catching up with you! As discussed, here's your <strong>{{ $tierLabel }}</strong> report,
       attached to this email — a full breakdown of where things stand today, the gaps we
       covered, and the opportunities to grow from here.</p>

    <p>You can also view it any time at this link: <a href="{{ $purchase->reportUrl() }}">{{ $purchase->reportUrl() }}</a></p>

    <p>Let us know if you have any questions — happy to help.</p>

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
