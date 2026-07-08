<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Project updates digest</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      {{-- Header --}}
      <tr>
        <td style="background:#4338ca;border-radius:8px 8px 0 0;padding:24px 32px;">
          <p style="margin:0;font-size:13px;color:#c7d2fe;letter-spacing:0.05em;text-transform:uppercase;">NEDS CRM &nbsp;·&nbsp; Project Updates Digest</p>
          <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;font-weight:700;">Good morning, {{ $user->name }}</h1>
          <p style="margin:6px 0 0;font-size:14px;color:#c7d2fe;">Here's where client updates and project activity stand across the team.</p>
        </td>
      </tr>

      {{-- Body --}}
      <tr>
        <td style="background:#ffffff;padding:28px 32px;border-radius:0 0 8px 8px;">

          {{-- Yesterday's drafts --}}
          @if ($yesterdaysDrafts->isNotEmpty())
            @php
              $approvedYesterday = $yesterdaysDrafts->where('visible_to_client', true)->count();
              $pendingYesterday = $yesterdaysDrafts->count() - $approvedYesterday;
            @endphp
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#4338ca;border-left:3px solid #4338ca;padding-left:10px;">
              Yesterday's client updates ({{ $yesterdaysDrafts->count() }})
            </h3>
            <p style="margin:0 0 28px;font-size:13px;color:#374151;">
              {{ $approvedYesterday }} approved &amp; sent to the client, {{ $pendingYesterday }} still awaiting review.
            </p>
          @endif

          {{-- Stale drafts --}}
          @if ($staleDrafts->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#b45309;border-left:3px solid #b45309;padding-left:10px;">
              Client updates awaiting review {{ $staleDays }}+ days ({{ $staleDrafts->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:28px;">
              <tr style="background:#f9fafb;">
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Project</th>
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Project Manager</th>
                <th style="padding:6px 10px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Waiting</th>
              </tr>
              @foreach ($staleDrafts as $note)
                @php($project = $note->notable)
                <tr style="border-bottom:1px solid #f3f4f6;">
                  <td style="padding:9px 10px;color:#111827;font-weight:500;">
                    {{ $project?->name ?? 'Project removed' }}
                    @if ($project?->customer)<span style="color:#9ca3af;font-weight:400;"> — {{ $project->customer->company_name }}</span>@endif
                  </td>
                  <td style="padding:9px 10px;color:#6b7280;">{{ $project?->owner?->name ?? '—' }}</td>
                  <td style="padding:9px 10px;text-align:right;white-space:nowrap;">
                    <span style="color:#b45309;font-size:12px;font-weight:600;">{{ (int) $note->created_at->diffInDays(now()) }}d ago</span>
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Quiet projects --}}
          @if ($quietProjects->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#b91c1c;border-left:3px solid #b91c1c;padding-left:10px;">
              Projects gone quiet {{ $quietDays }}+ days ({{ $quietProjects->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:28px;">
              <tr style="background:#f9fafb;">
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Project</th>
                <th style="padding:6px 10px;text-align:left;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Project Manager</th>
                <th style="padding:6px 10px;text-align:right;font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;border-bottom:1px solid #e5e7eb;">Last activity</th>
              </tr>
              @foreach ($quietProjects as $row)
                <tr style="border-bottom:1px solid #f3f4f6;">
                  <td style="padding:9px 10px;color:#111827;font-weight:500;">
                    {{ $row['project']->name }}
                    @if ($row['project']->customer)<span style="color:#9ca3af;font-weight:400;"> — {{ $row['project']->customer->company_name }}</span>@endif
                  </td>
                  <td style="padding:9px 10px;color:#6b7280;">{{ $row['project']->owner?->name ?? '—' }}</td>
                  <td style="padding:9px 10px;text-align:right;white-space:nowrap;">
                    @if ($row['lastActivityAt'])
                      <span style="color:#b91c1c;font-size:12px;font-weight:600;">{{ (int) $row['lastActivityAt']->diffInDays(now()) }}d ago</span>
                    @else
                      <span style="color:#b91c1c;font-size:12px;font-weight:600;">No activity yet</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Footer CTA --}}
          <div style="text-align:center;padding-top:16px;border-top:1px solid #e5e7eb;">
            <a href="{{ config('app.url') }}/projects"
               style="display:inline-block;background:#4338ca;color:#ffffff;text-decoration:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:600;">
              Open Project Updates
            </a>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              Niranjan Enterprises Digital Solutions &nbsp;·&nbsp; NEDS CRM<br>
              Sent daily at 9:15 AM IST when there's something to report.
            </p>
          </div>

        </td>
      </tr>

    </table>
  </td></tr>
</table>

</body>
</html>
