<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Recommendation Not Available | Niranjan Enterprises</title>
@include('offers.partials.styles')
</head>
<body>

<header class="top">
  <div class="container topin">
    <div class="logo">Niranjan Enterprises <span>Digital Solutions</span></div>
  </div>
</header>

<section class="hero" style="padding:70px 0">
  <div class="container center">
    <div class="eyebrow">We couldn't load this</div>
    <h1 style="font-size:clamp(28px,4vw,42px)">We couldn't load your personalized <em>recommendation</em></h1>
    <p style="max-width:560px;margin:0 auto 28px;color:#475467">यह link expire हो चुका है या उपलब्ध नहीं है. कोई बात नहीं — आप सीधे हमसे बात कर सकते हैं, या हमारी GBP Visibility Audit offer देख सकते हैं.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      @if (config('services.wadesk.marketing_number'))
        <a class="primary" href="https://wa.me/{{ config('services.wadesk.marketing_number') }}" target="_blank" rel="noopener">WhatsApp us →</a>
      @endif
      @if (config('company.phone'))
        <a class="primary" style="background:linear-gradient(135deg,#344054,#101828)" href="tel:{{ config('company.phone') }}">Call us: {{ config('company.phone') }}</a>
      @endif
    </div>
    <p style="margin-top:24px">
      <a href="{{ route('offers.visibility-audit') }}" style="color:var(--blue);font-weight:800">Or see our Google Business Profile Visibility Audit →</a>
    </p>
  </div>
</section>

<footer class="footer">
  <div class="container">© {{ date('Y') }} Niranjan Enterprises Digital Solutions. All rights reserved.</div>
</footer>

</body>
</html>
