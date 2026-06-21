<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937; max-width: 640px;">
    <h2 style="color:#dc2626;">SLA breach alert</h2>
    <p>The following open tickets have breached their SLA and need immediate attention:</p>

    @foreach ($tickets as $ticket)
        @php
            $overdueMinutes = (int) now()->diffInMinutes($ticket->sla_due_at, false) * -1;
            $overdueHours   = intdiv($overdueMinutes, 60);
            $overdueLabel   = $overdueHours >= 24
                ? floor($overdueHours / 24).'d '.($overdueHours % 24).'h overdue'
                : ($overdueHours > 0 ? $overdueHours.'h '.($overdueMinutes % 60).'m overdue' : $overdueMinutes.'m overdue');
            $ticketUrl = rtrim(config('app.url'), '/').'/tickets/'.$ticket->id;
        @endphp
        <table style="width:100%;border:1px solid #e5e7eb;border-radius:6px;border-collapse:collapse;margin-bottom:16px;">
            <tr style="background:#fef2f2;">
                <td style="padding:10px 14px;">
                    <strong style="font-size:15px;">
                        <a href="{{ $ticketUrl }}" style="color:#1d4ed8;text-decoration:none;">#{{ $ticket->id }} — {{ $ticket->subject }}</a>
                    </strong>
                    <span style="float:right;background:#dc2626;color:#fff;font-size:11px;padding:2px 8px;border-radius:12px;">{{ $overdueLabel }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding:10px 14px;font-size:13px;line-height:1.7;">
                    <b>Client:</b> {{ $ticket->customer?->company_name ?? 'Client removed' }}<br>
                    <b>Priority:</b> {{ $ticket->priority->label() }}<br>
                    <b>SLA due:</b> {{ $ticket->sla_due_at?->timezone(config('app.display_timezone'))->format('d M Y, g:i A') }}<br>
                    <b>Assignee:</b> {{ $ticket->assignee?->name ?? 'Unassigned' }}<br>
                    <b>Channel:</b> {{ ucfirst($ticket->channel ?? 'portal') }}<br>
                    <b>Status:</b> {{ ucfirst($ticket->status->value) }}
                </td>
            </tr>
            <tr>
                <td style="padding:8px 14px;border-top:1px solid #e5e7eb;">
                    <a href="{{ $ticketUrl }}" style="font-size:13px;color:#1d4ed8;">View ticket →</a>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="color:#6b7280;font-size:12px;margin-top:24px;">NEDS CRM — this alert was sent once when the breach was detected.</p>
</body>
</html>
