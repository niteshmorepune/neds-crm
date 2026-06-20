<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $contact->name }},</p>
    <p>We received a request to reset your {{ config('company.name') }} portal password. Click the button below to choose a new one:</p>
    <p><a href="{{ $url }}" style="background:#4f46e5;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Reset my password</a></p>
    <p style="color:#6b7280;font-size:12px;">If you didn't request this, you can ignore this email — your password will not change.</p>
    <p style="color:#6b7280;font-size:12px;">Or copy this link into your browser: {{ $url }}</p>
</body>
</html>
