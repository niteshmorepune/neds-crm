<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2>SLA breach alert</h2>
    <p>The following open tickets have breached their SLA and need attention:</p>
    <ul>
        @foreach ($tickets as $ticket)
            <li>
                #{{ $ticket->id }} — {{ $ticket->subject }} ({{ $ticket->customer->company_name }}),
                {{ $ticket->priority->label() }}, due {{ $ticket->sla_due_at?->timezone(config('app.timezone'))->format('d M Y, g:i A') }},
                assignee: {{ $ticket->assignee?->name ?? 'Unassigned' }}
            </li>
        @endforeach
    </ul>
    <p style="color:#6b7280;font-size:12px;">NEDS CRM</p>
</body>
</html>
