<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Lead Generation Funnel Audit | Niranjan Enterprises</title>
<meta name="description" content="Find out where your leads are getting lost — a professional Lead Generation Funnel Audit for ₹299. Lead source, landing page, forms, WhatsApp, calls and follow-up, all reviewed.">
<link rel="canonical" href="{{ route('offers.lead-generation-audit') }}">
<meta property="og:title" content="Lead Generation Funnel Audit — Niranjan Enterprises">
<meta property="og:description" content="Pehle pata karein leads kahan lose ho rahe hain — ₹299 mein.">
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
@include('offers.partials.styles')
</head>
<body>

@php $ctaLabel = $offerKey->cta(); @endphp

<header class="top">
  <div class="container topin">
    <div class="logo">Niranjan Enterprises <span>Digital Solutions</span></div>
    <div class="topnote">Lead Generation Funnel Audit · <strong>₹299</strong></div>
  </div>
</header>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow">Lead Generation Funnel Audit</div>
      <h1>आपको More <em>Leads</em> चाहिए?</h1>
      <p>पहले पता करें leads कहाँ lose हो रहे हैं। आपकी website, ads, forms और follow-up system में छोटे conversion gaps भी लगातार enquiries को रोक सकते हैं. हमारा Lead Generation Funnel Audit आपके current journey को review करके सबसे important leakage points identify करता है.</p>
      <ul class="points">
        <li>Lead Source Review</li>
        <li>Landing Page &amp; CTA Check</li>
        <li>WhatsApp &amp; Call Conversion</li>
        <li>Follow-up Process Review</li>
      </ul>
      <a class="primary" href="#buy" x-data x-on:click.prevent="$dispatch('open-offer-checkout'); document.getElementById('buy').scrollIntoView({behavior:'smooth'})">{{ $ctaLabel }}</a>
      <div class="micro">One-time audit · <b>Secure online payment</b> · Digital report</div>
    </div>
    <div class="hero-card">
      <h3>Lead Generation Opportunity Score</h3>
      <p>आपके funnel के हर step का review — source से लेकर follow-up तक — एक clear score और priority action list के साथ.</p>
      <div class="price-float"><small>Funnel Audit</small><strong>₹299</strong></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="center">
      <div class="kicker">The real problem</div>
      <h2>Traffic आ रहा है, पर <em style="color:var(--red);font-style:normal">enquiries</em> कम हैं?</h2>
      <p class="lead">Leads सिर्फ एक जगह नहीं खोते — वो हर step पर थोड़े-थोड़े lose होते हैं: गलत source, कमजोर landing page, धीमा follow-up.</p>
    </div>
    <div class="problem">
      <div class="card"><div class="icon">⌖</div><h3>Wrong या unclear source</h3><p>कौन सा channel असली leads दे रहा है और कौन सा सिर्फ traffic — बिना track किए बताना मुश्किल है.</p></div>
      <div class="card"><div class="icon">⚙</div><h3>Landing page/form में gaps</h3><p>Confusing CTA, लंबा form या धीमा page — हर एक potential customer को रोक सकता है.</p></div>
      <div class="card"><div class="icon">☎</div><h3>Follow-up में देरी</h3><p>WhatsApp और call follow-up समय पर न होने से गर्म leads भी ठंडे पड़ जाते हैं.</p></div>
    </div>
  </div>
</section>

<section class="section soft">
  <div class="container">
    <div class="center">
      <div class="kicker">What we check</div>
      <h2>आपका पूरा lead journey, एक-एक step करके</h2>
    </div>
    <div class="audit-grid">
      <div class="audit-item"><div class="icon">📍</div><div><h3>Lead Source</h3><p>कौन सा channel actual leads ला रहा है.</p></div></div>
      <div class="audit-item"><div class="icon">🖥</div><div><h3>Landing Page</h3><p>Message match, clarity और page speed.</p></div></div>
      <div class="audit-item"><div class="icon">🎯</div><div><h3>CTA</h3><p>क्या CTA clear और easy-to-act है.</p></div></div>
      <div class="audit-item"><div class="icon">📝</div><div><h3>Lead Form</h3><p>Length, fields और friction points.</p></div></div>
      <div class="audit-item"><div class="icon">💬</div><div><h3>WhatsApp Conversion</h3><p>Reply speed और conversation quality.</p></div></div>
      <div class="audit-item"><div class="icon">📞</div><div><h3>Call Conversion</h3><p>Pickup rate और first-call handling.</p></div></div>
      <div class="audit-item"><div class="icon">📊</div><div><h3>Conversion Tracking</h3><p>क्या आप असल में माप पा रहे हैं कि क्या काम कर रहा है.</p></div></div>
      <div class="audit-item"><div class="icon">🔁</div><div><h3>Follow-up Process</h3><p>कितनी बार, कितनी जल्दी follow-up हो रहा है.</p></div></div>
    </div>
  </div>
</section>

<section class="section" id="buy">
  <div class="container offer">
    <div class="offer-copy">
      <div class="kicker">The offer</div>
      <h2>Lead Generation Funnel Audit</h2>
      <p>यह एक <strong>diagnostic entry offer</strong> है — पूरी lead-generation service नहीं. मकसद है आपके funnel में सबसे बड़े leakage points और priority opportunities साफ़ तरीके से सामने लाना.</p>
      <ul class="offer-list">
        <li>Lead Generation Opportunity / Readiness Score</li>
        <li>Major leakage points की पूरी list</li>
        <li>Priority improvements — किसे पहले ठीक करें</li>
        <li>Recommended next steps</li>
      </ul>
    </div>
    <div class="buybox">
      <div class="buybox-head">
        <div><h3>Funnel Audit</h3></div>
        <span class="tag">Diagnostic</span>
      </div>
      <div class="price">₹299</div>
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
      <h2>Payment से लेकर recommendations तक</h2>
    </div>
    <div class="steps">
      <div class="step"><div class="num">STEP 1</div><h3>Submit your details</h3><p>Payment के साथ अपना WhatsApp/website details share करें.</p></div>
      <div class="step"><div class="num">STEP 2</div><h3>Complete payment</h3><p>₹299 का secure online payment.</p></div>
      <div class="step"><div class="num">STEP 3</div><h3>हमारी team review करती है</h3><p>आपके funnel का पूरा review — source से follow-up तक.</p></div>
      <div class="step"><div class="num">STEP 4</div><h3>Audit report मिलता है</h3><p>Score, leakage points और recommended next steps के साथ.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container trust">
    <div>
      <div class="kicker">Why Niranjan Enterprises</div>
      <h2>Maharashtra के business owners का <em>trusted</em> digital partner</h2>
      <p style="color:var(--muted)">SEO, Local SEO, Ads और Lead Generation पर 12+ साल का real experience — sirf theory नहीं.</p>
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
      <details><summary>इसमें exactly क्या include है?</summary><p>आपके lead source, landing page, CTA, lead form, WhatsApp/call conversion, tracking और follow-up process का पूरा review — एक Lead Generation Opportunity Score और priority next steps के साथ.</p></details>
      <details><summary>यह कितने समय में मिलता है?</summary><p>Payment के बाद हमारी team आपसे WhatsApp/call पर details लेकर audit शुरू करती है — timeline आपकी टीम इसी conversation में confirm करेगी.</p></details>
      <details><summary>क्या यह implementation भी include करता है?</summary><p>नहीं — यह एक diagnostic audit है, actual implementation (ads/website/CRM setup) एक अलग service के रूप में discuss होता है.</p></details>
      <details><summary>क्या payment के बाद campaigns manage भी होंगे?</summary><p>Audit में हम manage नहीं करते — findings के आधार पर अगर आप आगे बढ़ना चाहें तो हमारी team relevant implementation service आपके सामने रखेगी.</p></details>
      <details><summary>क्या बाद में monthly service में upgrade कर सकते हैं?</summary><p>हाँ — audit के findings के आधार पर, अगर सही लगे तो आप implementation/monthly service पर आगे बढ़ सकते हैं.</p></details>
    </div>
  </div>
</section>

<section class="final">
  <div class="container center">
    <h2>पहले पता करें leads कहाँ <em style="color:var(--yellow);font-style:normal">lose</em> हो रहे हैं</h2>
    <p>एक ₹299 का diagnostic step, जो आगे के हर investment को सही दिशा देता है.</p>
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
    <div class="sticky-price">Lead Generation Audit<strong>₹299</strong></div>
    <button type="button" class="primary" x-data x-on:click="$dispatch('open-offer-checkout')">Get Audit →</button>
  </div>
@endif

@include('offers.partials.checkout-script')

</body>
</html>
