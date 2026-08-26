<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $lead->name ?: 'there' }},</p>

    @if ($stage->value === 'payment_viewed')
        <p>You were just a step away from claiming your <strong>free Google Business Profile Audit</strong>
           — still interested? Pick up right where you left off.</p>
    @else
        <p>Your <strong>free Google Business Profile Audit</strong> is still available — see what's
           holding your listing back, no cost to check.</p>
    @endif

    <p style="margin: 24px 0;">
        <a href="{{ $offerUrl }}" style="background: #4f46e5; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            {{ $stage->value === 'payment_viewed' ? 'Complete my audit request' : 'View my free audit offer' }}
        </a>
    </p>

    <p style="color: #6b7280; font-size: 13px;">Or copy this link into your browser: {{ $offerUrl }}</p>

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
