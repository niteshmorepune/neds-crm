<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Website + Conversion Growth Audit | Niranjan Enterprises</title>
<meta name="description" content="Is your website just visible, or actually generating business? A professional Website + Conversion Growth Audit for ₹499 — performance, trust signals, CTAs and conversion opportunities reviewed.">
<link rel="canonical" href="{{ route('offers.website-growth-audit') }}">
<meta property="og:title" content="Website + Conversion Growth Audit — Niranjan Enterprises">
<meta property="og:description" content="Aapki website sirf dikh rahi hai — ya business bhi generate kar rahi hai? ₹499 mein pata karein.">
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
@include('offers.partials.styles')
</head>
<body>

@php $ctaLabel = $offerKey->cta(); @endphp

<header class="top">
  <div class="container topin">
    <div class="logo">Niranjan Enterprises <span>Digital Solutions</span></div>
    <div class="topnote">Website + Conversion Growth Audit · <strong>₹499</strong></div>
  </div>
</header>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow">Website + Conversion Growth Audit</div>
      <h1>आपकी website सिर्फ दिख रही है — या <em>business</em> भी generate कर रही है?</h1>
      <p>सिर्फ website होना काफी नहीं — उसे visitors को enquiries में बदलना भी आना चाहिए. हमारा Website + Conversion Growth Audit आपकी website के performance, trust signals और conversion points की पूरी review करता है.</p>
      <ul class="points">
        <li>Mobile UX &amp; Page Speed</li>
        <li>Trust Signals &amp; CTA Check</li>
        <li>WhatsApp, Calls &amp; Forms</li>
        <li>Local SEO Signals</li>
      </ul>
      <a class="primary" href="#buy" x-data x-on:click.prevent="$dispatch('open-offer-checkout'); document.getElementById('buy').scrollIntoView({behavior:'smooth'})">{{ $ctaLabel }}</a>
      <div class="micro">One-time audit · <b>Secure online payment</b> · Digital report</div>
    </div>
    <div class="hero-card">
      <h3>Website Growth Score</h3>
      <p>आपकी website के performance, trust और conversion points का पूरा review — एक score और priority action plan के साथ.</p>
      <div class="price-float"><small>Growth Audit</small><strong>₹499</strong></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="center">
      <div class="kicker">The real problem</div>
      <h2>Website दिखती अच्छी है, पर <em style="color:var(--red);font-style:normal">enquiries</em> नहीं आतीं?</h2>
      <p class="lead">एक अच्छी दिखने वाली website भी कमज़ोर CTA, धीमी speed या missing trust signals की वजह से business generate नहीं कर पाती.</p>
    </div>
    <div class="problem">
      <div class="card"><div class="icon">📱</div><h3>Mobile experience कमज़ोर हो सकती है</h3><p>ज़्यादातर visitors mobile से आते हैं — धीमी या confusing mobile experience उन्हें turn कर देती है.</p></div>
      <div class="card"><div class="icon">⚡</div><h3>Speed &amp; navigation issues</h3><p>धीमी loading और unclear navigation visitors को बिना action लिए वापस भेज देते हैं.</p></div>
      <div class="card"><div class="icon">☎</div><h3>Weak trust signals &amp; CTA</h3><p>बिना clear trust signals और CTA के, visitor enquiry करने का फैसला नहीं कर पाता.</p></div>
    </div>
  </div>
</section>

<section class="section soft">
  <div class="container">
    <div class="center">
      <div class="kicker">What we check</div>
      <h2>आपकी website का पूरा conversion review</h2>
    </div>
    <div class="audit-grid">
      <div class="audit-item"><div class="icon">👁</div><div><h3>Website Visibility</h3><p>Search में आपकी website कितनी discoverable है.</p></div></div>
      <div class="audit-item"><div class="icon">📱</div><div><h3>Mobile UX</h3><p>Mobile पर experience कितना smooth है.</p></div></div>
      <div class="audit-item"><div class="icon">⚡</div><div><h3>Performance</h3><p>Page speed और loading experience.</p></div></div>
      <div class="audit-item"><div class="icon">🧭</div><div><h3>Navigation</h3><p>क्या visitors आसानी से सही page तक पहुँचते हैं.</p></div></div>
      <div class="audit-item"><div class="icon">🛠</div><div><h3>Service Clarity</h3><p>क्या आपकी services clearly explain हो रही हैं.</p></div></div>
      <div class="audit-item"><div class="icon">🤝</div><div><h3>Trust Signals</h3><p>Reviews, credentials, proof points की मौजूदगी.</p></div></div>
      <div class="audit-item"><div class="icon">🎯</div><div><h3>CTA Visibility</h3><p>क्या call-to-action हर page पर clear है.</p></div></div>
      <div class="audit-item"><div class="icon">💬</div><div><h3>WhatsApp, Calls &amp; Forms</h3><p>Contact करने के तरीके कितने आसान और visible हैं.</p></div></div>
      <div class="audit-item"><div class="icon">📍</div><div><h3>Local SEO Signals</h3><p>GBP-to-website consistency और local relevance.</p></div></div>
      <div class="audit-item"><div class="icon">📈</div><div><h3>Conversion Opportunities</h3><p>सबसे बड़े quick-win opportunities कहाँ हैं.</p></div></div>
    </div>
  </div>
</section>

<section class="section" id="buy">
  <div class="container offer">
    <div class="offer-copy">
      <div class="kicker">The offer</div>
      <h2>Website + Conversion Growth Audit</h2>
      <p>आपकी website की पूरी review — visibility से लेकर conversion तक — एक <strong>Website Growth Score</strong> और priority action plan के साथ.</p>
      <ul class="offer-list">
        <li>Website Growth Score</li>
        <li>Priority issues की पूरी list</li>
        <li>Conversion opportunities</li>
        <li>Recommended fixes &amp; next steps</li>
      </ul>
    </div>
    <div class="buybox">
      <div class="buybox-head">
        <div><h3>Growth Audit</h3></div>
        <span class="tag">Diagnostic</span>
      </div>
      <div class="price">₹499</div>
      @if ($razorpayConfigured)
        <button type="button" class="primary buybtn" x-data x-on:click="$dispatch('open-offer-checkout')">{{ $ctaLabel }}</button>
      @else
        <span class="primary disabled buybtn">Coming soon</span>
      @endif
      <div class="secure">🔒 Secure payment · Razorpay</div>
    </div>
  </div>
</section>

<section class="section soft">
  <div class="container">
    <div class="center">
      <div class="kicker">The process</div>
      <h2>Payment से लेकर action plan तक</h2>
    </div>
    <div class="steps">
      <div class="step"><div class="num">STEP 1</div><h3>Submit your details</h3><p>अपनी website का link share करें.</p></div>
      <div class="step"><div class="num">STEP 2</div><h3>Complete payment</h3><p>₹499 का secure online payment.</p></div>
      <div class="step"><div class="num">STEP 3</div><h3>हमारी team review करती है</h3><p>Visibility से conversion तक पूरा review.</p></div>
      <div class="step"><div class="num">STEP 4</div><h3>Growth Score मिलता है</h3><p>Priority action plan के साथ.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container trust">
    <div>
      <div class="kicker">Why Niranjan Enterprises</div>
      <h2>Real websites, real <em style="color:var(--red);font-style:normal">conversions</em> — 12+ साल का experience</h2>
      <p style="color:var(--muted)">Website design, SEO और conversion optimization में real hands-on experience.</p>
    </div>
    <div class="statgrid">
      <div class="stat"><strong>66+</strong><span>Maharashtra businesses served</span></div>
      <div class="stat"><strong>12+</strong><span>years of digital experience</span></div>
      <div class="stat"><strong>4.9</strong><span>Google rating</span></div>
    </div>
  </div>
</section>

<section class="section soft">
  <div class="container">
    <div class="center"><div class="kicker">FAQ</div><h2>अक्सर पूछे जाने वाले सवाल</h2></div>
    <div class="faq">
      <details><summary>इसमें exactly क्या include है?</summary><p>आपकी website की visibility, mobile UX, performance, trust signals, CTA, WhatsApp/calls/forms, local SEO signals और conversion opportunities का पूरा review — Website Growth Score और priority action plan के साथ.</p></details>
      <details><summary>यह कितने समय में मिलता है?</summary><p>Payment के बाद हमारी team आपकी website review शुरू करती है — timeline conversation में confirm होगी.</p></details>
      <details><summary>क्या इसमें website redesign भी include है?</summary><p>नहीं — यह एक diagnostic audit है. Redesign/development findings के आधार पर एक अलग service के रूप में discuss होता है.</p></details>
      <details><summary>क्या आप audit के बाद website manage करेंगे?</summary><p>Audit में manage नहीं होता — findings के base पर अगर आप आगे implementation चाहें तो हमारी team उसे आपके सामने रखेगी.</p></details>
      <details><summary>क्या बाद में monthly service में upgrade कर सकते हैं?</summary><p>हाँ — findings के आधार पर आप website/SEO/Ads की monthly service पर आगे बढ़ सकते हैं.</p></details>
    </div>
  </div>
</section>

<section class="final">
  <div class="container center">
    <h2>पता करें आपकी website <em style="color:var(--yellow);font-style:normal">business</em> generate कर रही है या नहीं</h2>
    <p>एक ₹499 का diagnostic step, जो आपकी website को actual growth engine बनाने की दिशा दिखाता है.</p>
    @if ($razorpayConfigured)
      <button type="button" class="primary" x-data x-on:click="$dispatch('open-offer-checkout')">{{ $ctaLabel }}</button>
    @else
      <span class="primary disabled">Coming soon</span>
    @endif
  </div>
</section>

<footer class="footer">
  <div class="container">© {{ date('Y') }} Niranjan Enterprises Digital Solutions. All rights reserved.</div>
</footer>

@if ($razorpayConfigured)
  <div class="sticky">
    <div class="sticky-price">Website Growth Audit<strong>₹499</strong></div>
    <button type="button" class="primary" x-data x-on:click="$dispatch('open-offer-checkout')">Get Audit →</button>
  </div>
@endif

@include('offers.partials.checkout-script')

</body>
</html>
