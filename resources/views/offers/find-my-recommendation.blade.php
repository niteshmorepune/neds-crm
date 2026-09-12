<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Find My Recommendation | Niranjan Enterprises</title>
@include('offers.partials.styles')
<style>
.lookupbox{max-width:440px;margin:0 auto;background:#fff;border:1px solid #dbe3f0;border-radius:25px;box-shadow:var(--shadow);padding:32px;text-align:left}
.lookupbox label{display:block;font-size:13px;font-weight:800;color:#344054;margin-bottom:6px}
.lookupbox input{width:100%;padding:14px 16px;border:1px solid var(--line);border-radius:11px;font-size:16px;color:var(--ink);margin-bottom:16px}
.lookupbox input:focus{outline:2px solid var(--blue2);outline-offset:1px}
.lookupbox .primary{width:100%}
</style>
</head>
<body>

<header class="top">
  <div class="container topin">
    <div class="logo">Niranjan Enterprises <span>Digital Solutions</span></div>
  </div>
</header>

<section class="hero" style="padding:64px 0">
  <div class="container center">
    <div class="eyebrow">Thanks for reaching out</div>
    <h1 style="font-size:clamp(30px,4.5vw,46px)">Let's find your <em>personalized recommendation</em></h1>
    <p style="max-width:520px;margin:0 auto 32px;color:#475467">आपने अभी जो form भरा, उसी phone number से हम आपको आपकी सही recommendation दिखा सकते हैं.</p>

    <div class="lookupbox">
      @if ($error ?? null)
        <div class="buyerror" style="margin-bottom:16px">{{ $error }}</div>
      @endif
      <form method="POST" action="{{ route('offers.find-my-recommendation.lookup') }}">
        @csrf
        <label for="phone">Your phone number</label>
        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="10-digit mobile number" required autofocus inputmode="numeric">
        <button type="submit" class="primary">Show My Recommendation →</button>
      </form>
    </div>

    <p style="margin-top:24px;font-size:13px;color:var(--muted)">
      Prefer to just message us?
      @if (config('company.whatsapp'))
        <a href="https://wa.me/{{ config('company.whatsapp') }}" target="_blank" rel="noopener" style="color:var(--blue);font-weight:800">WhatsApp us →</a>
      @endif
    </p>
  </div>
</section>

<footer class="footer">
  <div class="container">© {{ date('Y') }} Niranjan Enterprises Digital Solutions. All rights reserved.</div>
</footer>

</body>
</html>
