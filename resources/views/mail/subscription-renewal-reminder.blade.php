<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $user->name }},</p>
    <p>
        <strong>{{ $subscription->name }}</strong>{{ $subscription->vendor ? ' ('.$subscription->vendor.')' : '' }}
        is due to renew on <strong>{{ $subscription->renewal_date->format('d M Y') }}</strong>
        for {{ \App\Support\Money::format($subscription->cost) }} ({{ $subscription->billing_cycle->label() }}).
    </p>
    @if ($subscription->notes)
        <p>{{ $subscription->notes }}</p>
    @endif
    <p>Thanks,<br>{{ config('company.name') }} CRM</p>
</body>
</html>
