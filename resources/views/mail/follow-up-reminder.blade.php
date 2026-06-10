<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2>Hi {{ $user->name }},</h2>
    <p>You have follow-ups due. Please action them today.</p>

    @if ($leads->isNotEmpty())
        <h3>Leads ({{ $leads->count() }})</h3>
        <ul>
            @foreach ($leads as $lead)
                <li>
                    {{ $lead->name }}{{ $lead->company ? ' — '.$lead->company : '' }}
                    (due {{ $lead->next_follow_up_at?->timezone(config('app.timezone'))->format('d M Y') }})
                </li>
            @endforeach
        </ul>
    @endif

    @if ($deals->isNotEmpty())
        <h3>Deals ({{ $deals->count() }})</h3>
        <ul>
            @foreach ($deals as $deal)
                <li>
                    {{ $deal->title }} — {{ $deal->stage->label() }}
                    (due {{ $deal->next_follow_up_at?->timezone(config('app.timezone'))->format('d M Y') }})
                </li>
            @endforeach
        </ul>
    @endif

    <p style="color:#6b7280;font-size:12px;">NEDS CRM</p>
</body>
</html>
