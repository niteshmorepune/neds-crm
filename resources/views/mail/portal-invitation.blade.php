<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $contact->name }},</p>
    <p>{{ config('company.name') }} has given you access to the client portal, where you can view your invoices, projects and support tickets.</p>
    <p>Set your password to get started:</p>
    <p><a href="{{ $url }}" style="background:#4f46e5;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Set my password</a></p>
    <p style="color:#6b7280;font-size:12px;">If you didn't expect this, you can ignore this email.</p>
</body>
</html>
