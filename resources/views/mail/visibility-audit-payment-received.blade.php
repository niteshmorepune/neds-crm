<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <p>Hi {{ $purchase->payer_name ?: 'there' }},</p>
    <p>Thank you for your payment — we've received <strong>{{ $amountPaid }}</strong> for your
       <strong>{{ $tierLabel }}</strong>.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 420px; margin: 12px 0;">
        <tr style="background: #f9fafb;">
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Audit</td>
            <td style="border: 1px solid #e5e7eb;">{{ $tierLabel }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Amount paid</td>
            <td style="border: 1px solid #e5e7eb;">{{ $amountPaid }}</td>
        </tr>
        <tr style="background: #f9fafb;">
            <td style="border: 1px solid #e5e7eb; font-weight: bold;">Payment reference</td>
            <td style="border: 1px solid #e5e7eb;">{{ $purchase->razorpay_payment_id }}</td>
        </tr>
    </table>

    <p>Our team will begin work shortly and will reach out here on email and on WhatsApp with any
       questions and to keep you posted on progress.</p>

    <p>Thank you for choosing {{ config('company.name') }}!</p>
</body>
</html>
