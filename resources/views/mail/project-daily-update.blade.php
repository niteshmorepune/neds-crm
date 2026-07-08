<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $project->customer?->company_name ?? 'Client' }},</p>

    <p>Here's an update on your project <strong>{{ $project->name }}</strong>:</p>

    <blockquote style="border-left:3px solid #e5e7eb;padding-left:10px;color:#374151;">{{ $note->body }}</blockquote>

    <p>You can view the full project timeline anytime from your client portal.</p>

    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
