<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Stagnation alert</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      {{-- Header --}}
      <tr>
        <td style="background:#b45309;border-radius:8px 8px 0 0;padding:24px 32px;">
          <p style="margin:0;font-size:13px;color:#fde68a;letter-spacing:0.05em;text-transform:uppercase;">NEDS CRM &nbsp;·&nbsp; Stagnation Alert</p>
          <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;font-weight:700;">Records going cold, {{ $user->name }}</h1>
          <p style="margin:6px 0 0;font-size:14px;color:#fde68a;">
            {{ $leads->count() + $deals->count() }} record(s) haven't had any activity in a while and need your attention.
          </p>
        </td>
      </tr>

      {{-- Body --}}
      <tr>
        <td style="background:#ffffff;padding:28px 32px;border-radius:0 0 8px 8px;">

          {{-- Stagnant leads --}}
          @if ($leads->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#b45309;border-left:3px solid #b45309;padding-left:10px;">
              Leads — no touch in {{ $leadDays }}+ days ({{ $leads->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:28px;">
              <tr style="background:#f9fafb;">
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Lead</th>
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Status</th>
                <th style="padding:6px 10px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Created</th>
                <th style="padding:6px 10px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Last updated</th>
              </tr>
              @foreach ($leads as $lead)
                @php($daysSince = (int) $lead->updated_at->diffInDays(now()))
                <tr style="border-bottom:1px solid #f3f4f6;">
                  <td style="padding:9px 10px;color:#111827;font-weight:500;">
                    {{ $lead->name }}
                    @if ($lead->company)<span style="color:#9ca3af;font-weight:400;"> — {{ $lead->company }}</span>@endif
                  </td>
                  <td style="padding:9px 10px;color:#6b7280;">{{ $lead->status->label() }}</td>
                  <td style="padding:9px 10px;color:#6b7280;text-align:right;white-space:nowrap;font-size:12px;">{{ $lead->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td>
                  <td style="padding:9px 10px;text-align:right;white-space:nowrap;">
                    <span style="color:#b45309;font-size:12px;font-weight:600;">{{ $daysSince }}d ago</span>
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Stagnant deals --}}
          @if ($deals->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#b45309;border-left:3px solid #b45309;padding-left:10px;">
              Deals — no touch in {{ $dealDays }}+ days ({{ $deals->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:28px;">
              <tr style="background:#f9fafb;">
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Deal</th>
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Stage</th>
                <th style="padding:6px 10px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Value</th>
                <th style="padding:6px 10px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Last updated</th>
              </tr>
              @foreach ($deals as $deal)
                @php($daysSince = (int) $deal->updated_at->diffInDays(now()))
                <tr style="border-bottom:1px solid #f3f4f6;">
                  <td style="padding:9px 10px;color:#111827;font-weight:500;">
                    {{ $deal->title }}
                    @if ($deal->customer)<span style="color:#9ca3af;font-weight:400;"> — {{ $deal->customer->company_name }}</span>@endif
                  </td>
                  <td style="padding:9px 10px;color:#6b7280;">{{ $deal->stage->label() }}</td>
                  <td style="padding:9px 10px;color:#6b7280;text-align:right;white-space:nowrap;font-size:12px;">{{ \App\Support\Money::format($deal->value) }}</td>
                  <td style="padding:9px 10px;text-align:right;white-space:nowrap;">
                    <span style="color:#b45309;font-size:12px;font-weight:600;">{{ $daysSince }}d ago</span>
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- What to do --}}
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:14px 16px;margin-bottom:20px;">
            <p style="margin:0;font-size:13px;color:#92400e;">
              <strong>What to do:</strong> Log a call, add a note, or update the record's status in the CRM.
              Even a brief note counts as a touch and resets the stagnation clock.
            </p>
          </div>

          {{-- Footer CTA --}}
          <div style="text-align:center;padding-top:16px;border-top:1px solid #e5e7eb;">
            <a href="{{ config('app.url') }}/dashboard"
               style="display:inline-block;background:#b45309;color:#ffffff;text-decoration:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:600;">
              Open CRM Dashboard
            </a>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              Niranjan Enterprises Digital Solutions &nbsp;·&nbsp; NEDS CRM<br>
              Sent daily at 10:00 AM IST when stagnant records are detected.
            </p>
          </div>

        </td>
      </tr>

    </table>
  </td></tr>
</table>

</body>
</html>
