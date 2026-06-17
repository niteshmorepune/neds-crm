<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Monthly reports due</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

      {{-- Header --}}
      <tr>
        <td style="background:#4f46e5;border-radius:8px 8px 0 0;padding:24px 32px;">
          <p style="margin:0;font-size:13px;color:#c7d2fe;letter-spacing:0.05em;text-transform:uppercase;">NEDS CRM</p>
          <h1 style="margin:6px 0 0;font-size:22px;color:#ffffff;font-weight:700;">Monthly Reports Due</h1>
          <p style="margin:6px 0 0;font-size:14px;color:#c7d2fe;">
            Hi {{ $recipient->name }} — it's the end of {{ $date->format('F Y') }}.
            Time to prepare and send monthly reports to your retainer clients.
          </p>
        </td>
      </tr>

      {{-- Body --}}
      <tr>
        <td style="background:#ffffff;padding:28px 32px;border-radius:0 0 8px 8px;">

          {{-- Summary strip --}}
          <div style="background:#eff6ff;border-radius:8px;padding:16px 20px;margin-bottom:24px;text-align:center;">
            <div style="font-size:32px;font-weight:700;color:#2563eb;">{{ $customers->count() }}</div>
            <div style="font-size:13px;color:#1d4ed8;margin-top:2px;">Retainer client(s) need a monthly report</div>
          </div>

          {{-- Client list --}}
          <h3 style="margin:0 0 12px;font-size:14px;font-weight:700;color:#4f46e5;border-left:3px solid #4f46e5;padding-left:10px;">
            Clients to report to this month
          </h3>

          <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:28px;border-collapse:collapse;">
            <tr style="background:#f9fafb;">
              <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #e5e7eb;">Client</th>
              <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #e5e7eb;">Contact email</th>
              <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;border-bottom:2px solid #e5e7eb;">Services</th>
            </tr>
            @foreach ($customers as $customer)
              @php
                $email = $customer->primaryContact?->email ?: $customer->email ?: '—';
                $services = $customer->recurringInvoices
                    ->map(fn ($ri) => $ri->service?->name)
                    ->filter()
                    ->unique()
                    ->implode(', ');
              @endphp
              <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:10px 10px;color:#111827;font-weight:500;">{{ $customer->company_name }}</td>
                <td style="padding:10px 10px;color:#4f46e5;">{{ $email }}</td>
                <td style="padding:10px 10px;color:#6b7280;">{{ $services ?: '—' }}</td>
              </tr>
            @endforeach
          </table>

          {{-- Checklist --}}
          <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
            <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#15803d;">Quick checklist before sending:</p>
            <ul style="margin:0;padding-left:18px;font-size:13px;color:#166534;line-height:1.8;">
              <li>Summarise work done this month per service</li>
              <li>Include key metrics / results (rankings, impressions, conversions, etc.)</li>
              <li>Note any pending action items or upcoming milestones</li>
              <li>Attach screenshots or reports where applicable</li>
            </ul>
          </div>

          {{-- Footer CTA --}}
          <div style="text-align:center;margin-top:8px;padding-top:20px;border-top:1px solid #e5e7eb;">
            <a href="{{ config('app.url') }}/clients"
               style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:600;">
              Open Clients in CRM
            </a>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              Niranjan Enterprises Digital Solutions &nbsp;·&nbsp; NEDS CRM<br>
              You're receiving this as an admin, manager, or accounts team member.
            </p>
          </div>

        </td>
      </tr>

    </table>
  </td></tr>
</table>

</body>
</html>
