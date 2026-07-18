<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Your week ahead</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      {{-- Header --}}
      <tr>
        <td style="background:#4f46e5;border-radius:8px 8px 0 0;padding:24px 32px;">
          <p style="margin:0;font-size:13px;color:#c7d2fe;letter-spacing:0.05em;text-transform:uppercase;">NEDS CRM</p>
          <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;font-weight:700;">Good morning, {{ $user->name }} 👋</h1>
          <p style="margin:6px 0 0;font-size:14px;color:#c7d2fe;">{{ $date->format('l, d F Y') }} &nbsp;·&nbsp; Your week at a glance.</p>
        </td>
      </tr>

      {{-- Body --}}
      <tr>
        <td style="background:#ffffff;padding:28px 32px;border-radius:0 0 8px 8px;">

          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td style="background:#eef2ff;border-radius:6px;padding:16px 18px;">
                <p style="margin:0;font-size:14px;line-height:1.6;color:#3730a3;">🤖 {{ $summary }}</p>
              </td>
            </tr>
          </table>

          {{-- Footer CTA --}}
          <div style="text-align:center;margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb;">
            <a href="{{ config('app.url') }}/reports/business-overview"
               style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:600;">
              Open Business Overview
            </a>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              Full reports:
              <a href="{{ config('app.url') }}/reports/business-overview" style="color:#6366f1;">Business Overview</a> ·
              <a href="{{ config('app.url') }}/reports/cash-forecast" style="color:#6366f1;">Cash Forecast</a> ·
              <a href="{{ config('app.url') }}/client-radar" style="color:#6366f1;">Client Radar</a>
            </p>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              Niranjan Enterprises Digital Solutions &nbsp;·&nbsp; NEDS CRM<br>
              You're receiving this because you're an Admin/Manager.
            </p>
          </div>

        </td>
      </tr>

    </table>
  </td></tr>
</table>

</body>
</html>
