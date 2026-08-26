<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $lead->name ?: 'there' }},</p>
    <p>Thanks for your interest in a <strong>free Google Business Profile Audit</strong> for your
       business! See what's holding your listing back — tap below to check it out.</p>

    <p style="margin: 24px 0;">
        <a href="{{ $offerUrl }}" style="background: #4f46e5; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            View my free audit offer
        </a>
    </p>

    <p style="color: #6b7280; font-size: 13px;">Or copy this link into your browser: {{ $offerUrl }}</p>

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
