<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Your day ahead</title>
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
          <p style="margin:6px 0 0;font-size:14px;color:#c7d2fe;">{{ $date->format('l, d F Y') }} &nbsp;·&nbsp; Here's your day at a glance.</p>
        </td>
      </tr>

      {{-- Body --}}
      <tr>
        <td style="background:#ffffff;padding:28px 32px;border-radius:0 0 8px 8px;">

          @php
            $total = $overdueTasks->count() + $dueTodayTasks->count() + $callFollowUps->count()
                   + $leadFollowUps->count() + $dealFollowUps->count() + $openTickets->count();
          @endphp

          @if ($aiSummary)
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
              <td style="background:#eef2ff;border-radius:6px;padding:14px 16px;">
                <p style="margin:0;font-size:13px;color:#4338ca;">🤖 {{ $aiSummary }}</p>
              </td>
            </tr>
          </table>
          @endif

          {{-- Summary strip --}}
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
            <tr>
              @if ($overdueTasks->isNotEmpty())
              <td style="text-align:center;padding:12px 8px;background:#fef2f2;border-radius:6px;margin-right:4px;">
                <div style="font-size:22px;font-weight:700;color:#dc2626;">{{ $overdueTasks->count() }}</div>
                <div style="font-size:11px;color:#b91c1c;margin-top:2px;">Overdue tasks</div>
              </td>
              @endif
              @if ($dueTodayTasks->isNotEmpty())
              <td style="width:8px;"></td>
              <td style="text-align:center;padding:12px 8px;background:#eff6ff;border-radius:6px;">
                <div style="font-size:22px;font-weight:700;color:#2563eb;">{{ $dueTodayTasks->count() }}</div>
                <div style="font-size:11px;color:#1d4ed8;margin-top:2px;">Due today</div>
              </td>
              @endif
              @if ($callFollowUps->isNotEmpty())
              <td style="width:8px;"></td>
              <td style="text-align:center;padding:12px 8px;background:#fffbeb;border-radius:6px;">
                <div style="font-size:22px;font-weight:700;color:#d97706;">{{ $callFollowUps->count() }}</div>
                <div style="font-size:11px;color:#b45309;margin-top:2px;">Call follow-ups</div>
              </td>
              @endif
              @if ($openTickets->isNotEmpty())
              <td style="width:8px;"></td>
              <td style="text-align:center;padding:12px 8px;background:#f0fdf4;border-radius:6px;">
                <div style="font-size:22px;font-weight:700;color:#16a34a;">{{ $openTickets->count() }}</div>
                <div style="font-size:11px;color:#15803d;margin-top:2px;">Open tickets</div>
              </td>
              @endif
            </tr>
          </table>

          {{-- Overdue tasks --}}
          @if ($overdueTasks->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#dc2626;border-left:3px solid #dc2626;padding-left:10px;">
              Overdue Tasks ({{ $overdueTasks->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;">
              @foreach ($overdueTasks as $task)
                <tr style="background:#fef2f2;border-bottom:1px solid #fee2e2;">
                  <td style="padding:8px 10px;color:#111827;">
                    {{ $task->title }}
                    @if ($task->project) <span style="color:#9ca3af;"> — {{ $task->project->name }}</span> @endif
                  </td>
                  <td style="padding:8px 10px;color:#dc2626;text-align:right;white-space:nowrap;font-size:12px;">
                    Due {{ $task->due_date->format('d M') }}
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Due today --}}
          @if ($dueTodayTasks->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#2563eb;border-left:3px solid #2563eb;padding-left:10px;">
              Due Today ({{ $dueTodayTasks->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;">
              @foreach ($dueTodayTasks as $task)
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                  <td style="padding:8px 10px;color:#111827;">
                    {{ $task->title }}
                    @if ($task->project) <span style="color:#9ca3af;"> — {{ $task->project->name }}</span> @endif
                  </td>
                  <td style="padding:8px 10px;color:#6b7280;text-align:right;white-space:nowrap;font-size:12px;">
                    {{ $task->priority->label() }} priority &nbsp;·&nbsp; {{ $task->status->label() }}
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Call follow-ups --}}
          @if ($callFollowUps->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#d97706;border-left:3px solid #d97706;padding-left:10px;">
              Call Follow-ups ({{ $callFollowUps->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;">
              @foreach ($callFollowUps as $call)
                @php
                  $callName = match (true) {
                      $call->callable instanceof \App\Models\Customer => $call->callable->company_name,
                      $call->callable instanceof \App\Models\Lead => $call->callable->name,
                      default => '—',
                  };
                @endphp
                <tr style="background:#fffbeb;border-bottom:1px solid #fde68a;">
                  <td style="padding:8px 10px;color:#111827;">
                    {{ $callName }}
                    @if ($call->next_action) <span style="color:#9ca3af;"> — {{ $call->next_action }}</span> @endif
                  </td>
                  <td style="padding:8px 10px;color:#d97706;text-align:right;white-space:nowrap;font-size:12px;">
                    {{ $call->follow_up_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Lead follow-ups --}}
          @if ($leadFollowUps->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#7c3aed;border-left:3px solid #7c3aed;padding-left:10px;">
              Lead Follow-ups ({{ $leadFollowUps->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;">
              @foreach ($leadFollowUps as $lead)
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                  <td style="padding:8px 10px;color:#111827;">
                    {{ $lead->name }}{{ $lead->company ? ' — '.$lead->company : '' }}
                    <span style="color:#9ca3af;font-size:11px;"> ({{ $lead->status->label() }})</span>
                  </td>
                  <td style="padding:8px 10px;color:#6b7280;text-align:right;white-space:nowrap;font-size:12px;">
                    {{ $lead->next_follow_up_at->timezone(config('app.display_timezone'))->format('d M') }}
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Deal follow-ups --}}
          @if ($dealFollowUps->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#0891b2;border-left:3px solid #0891b2;padding-left:10px;">
              Deal Follow-ups ({{ $dealFollowUps->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;">
              @foreach ($dealFollowUps as $deal)
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                  <td style="padding:8px 10px;color:#111827;">
                    {{ $deal->title }}
                    @if ($deal->customer) <span style="color:#9ca3af;"> — {{ $deal->customer->company_name }}</span> @endif
                    <span style="color:#9ca3af;font-size:11px;"> ({{ $deal->stage->label() }})</span>
                  </td>
                  <td style="padding:8px 10px;color:#6b7280;text-align:right;white-space:nowrap;font-size:12px;">
                    {{ $deal->next_follow_up_at->timezone(config('app.display_timezone'))->format('d M') }}
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Open tickets --}}
          @if ($openTickets->isNotEmpty())
            <h3 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#16a34a;border-left:3px solid #16a34a;padding-left:10px;">
              Open Tickets ({{ $openTickets->count() }})
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;">
              @foreach ($openTickets as $ticket)
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                  <td style="padding:8px 10px;color:#111827;">
                    {{ $ticket->subject }}
                    @if ($ticket->customer) <span style="color:#9ca3af;"> — {{ $ticket->customer->company_name }}</span> @endif
                  </td>
                  <td style="padding:8px 10px;text-align:right;white-space:nowrap;font-size:12px;">
                    @if ($ticket->sla_due_at)
                      @php($slaPast = $ticket->sla_due_at->isPast())
                      <span style="color:{{ $slaPast ? '#dc2626' : '#6b7280' }};">
                        SLA {{ $slaPast ? 'breached' : $ticket->sla_due_at->timezone(config('app.display_timezone'))->format('d M, g:i A') }}
                      </span>
                    @else
                      <span style="color:#6b7280;">{{ $ticket->priority->label() }}</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </table>
          @endif

          {{-- Footer CTA --}}
          <div style="text-align:center;margin-top:12px;padding-top:20px;border-top:1px solid #e5e7eb;">
            <a href="{{ config('app.url') }}/dashboard"
               style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:600;">
              Open CRM Dashboard
            </a>
            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
              Niranjan Enterprises Digital Solutions &nbsp;·&nbsp; NEDS CRM<br>
              You're receiving this because you're an active team member.
            </p>
          </div>

        </td>
      </tr>

    </table>
  </td></tr>
</table>

</body>
</html>
