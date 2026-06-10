<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Dear {{ $ticket->customer->company_name }},</p>

    @switch($kind)
        @case('created')
            <p>We've received your support ticket <strong>#{{ $ticket->id }} — {{ $ticket->subject }}</strong> and our team is on it.</p>
            @break
        @case('replied')
            <p>There's a new update on your ticket <strong>#{{ $ticket->id }} — {{ $ticket->subject }}</strong>:</p>
            @if ($reply)<blockquote style="border-left:3px solid #e5e7eb;padding-left:10px;color:#374151;">{{ $reply->body }}</blockquote>@endif
            @break
        @case('resolved')
            <p>Your ticket <strong>#{{ $ticket->id }} — {{ $ticket->subject }}</strong> has been marked resolved. If anything's still outstanding, just reply and we'll reopen it.</p>
            @break
    @endswitch

    <p>Priority: {{ $ticket->priority->label() }}</p>
    <p>Thank you,<br>{{ config('company.name') }}</p>
</body>
</html>
