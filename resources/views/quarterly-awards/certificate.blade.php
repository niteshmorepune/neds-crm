<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; margin: 0; padding-top: 90px; }
        .frame { border: 6px solid #4f46e5; padding: 50px; text-align: center; }
        .brand { font-size: 16px; font-weight: bold; color: #4f46e5; letter-spacing: 1px; text-transform: uppercase; }
        .kicker { margin-top: 40px; font-size: 13px; color: #6b7280; letter-spacing: 2px; text-transform: uppercase; }
        .title { margin-top: 10px; font-size: 30px; font-weight: bold; color: #111827; }
        .presented { margin-top: 30px; font-size: 12px; color: #6b7280; }
        .name { margin-top: 8px; font-size: 26px; font-weight: bold; color: #4f46e5; }
        .period { margin-top: 4px; font-size: 13px; color: #6b7280; }
        .citation { margin: 30px auto 0; max-width: 560px; font-size: 13px; line-height: 1.6; color: #374151; }
        .footer { margin-top: 60px; }
        .signature { display: inline-block; width: 220px; border-top: 1px solid #9ca3af; padding-top: 6px; font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    @php($company = config('company'))

    <div class="frame">
        <div class="brand">{{ $company['name'] }}</div>
        <div class="kicker">Certificate of Recognition</div>
        <div class="title">{{ $award->title() }}</div>

        <div class="presented">This certificate is proudly presented to</div>
        <div class="name">{{ $award->user->name }}</div>
        <div class="period">{{ $award->periodLabel() }}</div>

        @if ($award->citation)
            <div class="citation">{{ $award->citation }}</div>
        @endif

        <div class="footer">
            <span class="signature">Authorized Signatory</span>
        </div>
    </div>
</body>
</html>
