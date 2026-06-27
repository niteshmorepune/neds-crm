<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Invoice {{ $invoice->invoice_number ?? 'Notification' }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
  <tr><td align="center">
    <table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;">

      {{-- Header --}}
      <tr>
        <td style="background:#1e293b;border-radius:8px 8px 0 0;padding:24px 32px;">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td>
                <p style="margin:0;font-size:12px;color:#94a3b8;letter-spacing:0.08em;text-transform:uppercase;">{{ config('company.name') }}</p>
                <h1 style="margin:4px 0 0;font-size:20px;color:#ffffff;font-weight:700;">Tax Invoice</h1>
              </td>
              <td style="text-align:right;vertical-align:top;">
                <div style="display:inline-block;background:#4f46e5;border-radius:6px;padding:6px 16px;">
                  <span style="font-size:14px;color:#ffffff;font-weight:700;">
                    {{ $invoice->invoice_number ?? 'PENDING' }}
                  </span>
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      {{-- Divider band --}}
      <tr>
        <td style="background:#4f46e5;height:3px;"></td>
      </tr>

      {{-- Body --}}
      <tr>
        <td style="background:#ffffff;padding:28px 32px;border-radius:0 0 8px 8px;">

          {{-- Greeting --}}
          <p style="margin:0 0 20px;font-size:15px;color:#374151;">
            Dear <strong>{{ $invoice->customer?->company_name ?? 'Client' }}</strong>,
          </p>
          <p style="margin:0 0 20px;font-size:14px;color:#6b7280;line-height:1.6;">
            Please find below your invoice details. Kindly review and process the payment by the due date.
          </p>

          {{-- Invoice summary card --}}
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:24px;">
            <tr>
              <td style="padding:20px 24px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="width:50%;vertical-align:top;padding-bottom:12px;">
                      <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;">Invoice Number</div>
                      <div style="font-size:15px;font-weight:700;color:#1e293b;">{{ $invoice->invoice_number ?? '—' }}</div>
                    </td>
                    <td style="width:50%;vertical-align:top;padding-bottom:12px;text-align:right;">
                      <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;">Amount Due</div>
                      <div style="font-size:22px;font-weight:700;color:#4f46e5;">{{ $total }}</div>
                    </td>
                  </tr>
                  <tr>
                    <td style="vertical-align:top;padding-top:8px;border-top:1px solid #e2e8f0;">
                      <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;">Issue Date</div>
                      <div style="font-size:13px;color:#374151;">{{ $invoice->issue_date->format('d M Y') }}</div>
                    </td>
                    @if ($invoice->due_date)
                    <td style="vertical-align:top;padding-top:8px;border-top:1px solid #e2e8f0;text-align:right;">
                      <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px;">Due Date</div>
                      <div style="font-size:13px;font-weight:600;color:#dc2626;">{{ $invoice->due_date->format('d M Y') }}</div>
                    </td>
                    @endif
                  </tr>
                </table>
              </td>
            </tr>
          </table>

          {{-- Milestone installments --}}
          @if ($invoice->milestones->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.05em;">Installment Schedule</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;border-collapse:collapse;">
              <thead>
                <tr style="background:#f1f5f9;">
                  <th style="text-align:left;padding:8px 12px;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Installment</th>
                  <th style="text-align:center;padding:8px 12px;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">%</th>
                  <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Amount</th>
                  <th style="text-align:right;padding:8px 12px;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;border-bottom:1px solid #e2e8f0;">Due</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($invoice->milestones as $milestone)
                  <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:9px 12px;color:#1e293b;">{{ $milestone->title }}</td>
                    <td style="padding:9px 12px;color:#64748b;text-align:center;">{{ rtrim(rtrim($milestone->percentage, '0'), '.') }}%</td>
                    <td style="padding:9px 12px;color:#1e293b;font-weight:600;text-align:right;">{{ \App\Support\Money::format($milestone->amount) }}</td>
                    <td style="padding:9px 12px;color:#64748b;text-align:right;">{{ $milestone->due_date?->format('d M Y') ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif

          {{-- Amount in words --}}
          <p style="margin:0 0 24px;font-size:12px;color:#64748b;font-style:italic;background:#f8fafc;padding:10px 14px;border-left:3px solid #4f46e5;border-radius:0 4px 4px 0;">
            <strong style="color:#374151;">Amount in words:</strong> {{ $invoice->amountInWords() }}
          </p>

          {{-- Bank account details --}}
          @php($company = config('company'))
          @if ($company['account_number'] || $company['upi_id'])
            <h3 style="margin:0 0 12px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.05em;">Payment Details</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:24px;">
              <tr>
                <td style="padding:16px 20px;">
                  @if ($company['bank_name'] || $company['account_number'])
                    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:{{ $company['upi_id'] ? '12px' : '0' }};">
                      @if ($company['bank_name'])
                        <tr>
                          <td style="padding:3px 0;color:#15803d;font-weight:600;width:40%;">Bank</td>
                          <td style="padding:3px 0;color:#1e293b;">{{ $company['bank_name'] }}</td>
                        </tr>
                      @endif
                      @if ($company['account_name'])
                        <tr>
                          <td style="padding:3px 0;color:#15803d;font-weight:600;">Account Name</td>
                          <td style="padding:3px 0;color:#1e293b;">{{ $company['account_name'] }}</td>
                        </tr>
                      @endif
                      @if ($company['account_number'])
                        <tr>
                          <td style="padding:3px 0;color:#15803d;font-weight:600;">Account No.</td>
                          <td style="padding:3px 0;color:#1e293b;font-weight:700;letter-spacing:0.05em;">{{ $company['account_number'] }}</td>
                        </tr>
                      @endif
                      @if ($company['ifsc_code'])
                        <tr>
                          <td style="padding:3px 0;color:#15803d;font-weight:600;">IFSC Code</td>
                          <td style="padding:3px 0;color:#1e293b;font-weight:600;">{{ $company['ifsc_code'] }}</td>
                        </tr>
                      @endif
                      @if ($company['account_type'])
                        <tr>
                          <td style="padding:3px 0;color:#15803d;font-weight:600;">Account Type</td>
                          <td style="padding:3px 0;color:#1e293b;">{{ $company['account_type'] }}</td>
                        </tr>
                      @endif
                    </table>
                  @endif
                  @if ($company['upi_id'])
                    <div style="font-size:13px;color:#15803d;font-weight:600;">UPI ID: <span style="color:#1e293b;font-weight:700;">{{ $company['upi_id'] }}</span></div>
                  @endif
                </td>
              </tr>
            </table>
          @endif

          {{-- Company + client GSTIN --}}
          <table width="100%" cellpadding="0" cellspacing="0" style="font-size:12px;color:#94a3b8;margin-bottom:24px;">
            <tr>
              <td>Our GSTIN: <strong style="color:#64748b;">{{ $company['gstin'] }}</strong></td>
              @if ($invoice->customer?->gstin)
                <td style="text-align:right;">Client GSTIN: <strong style="color:#64748b;">{{ $invoice->customer->gstin }}</strong></td>
              @endif
            </tr>
          </table>

          {{-- CTA --}}
          <div style="text-align:center;margin-top:8px;padding-top:20px;border-top:1px solid #e2e8f0;">
            <p style="margin:0 0 14px;font-size:13px;color:#6b7280;">Questions about this invoice? Reply to this email or contact us.</p>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              {{ $company['name'] }}<br>
              {{ $company['address'] }}<br>
              {{ $company['email'] }}@if ($company['phone']) &nbsp;·&nbsp; {{ $company['phone'] }} @endif
            </p>
          </div>

        </td>
      </tr>

    </table>
  </td></tr>
</table>

</body>
</html>
