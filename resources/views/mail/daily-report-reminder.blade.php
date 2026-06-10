<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $user->name }},</p>
    <p>This is a friendly reminder to submit your daily report for today before you log off.</p>
    <p>Thanks,<br>{{ config('company.name') }}</p>
</body>
</html>
