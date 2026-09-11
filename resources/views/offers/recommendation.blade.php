<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Your Personalized Recommendation | Niranjan Enterprises</title>
@include('offers.partials.styles')
</head>
<body>

<header class="top">
  <div class="container topin">
    <div class="logo">Niranjan Enterprises <span>Digital Solutions</span></div>
    <div class="topnote">Your Personalized Recommendation</div>
  </div>
</header>

<section class="hero" style="padding:38px 0 54px">
  <div class="container">
    <div class="eyebrow" style="margin-bottom:14px">Based on what you shared</div>
    <p style="color:var(--muted);font-size:14px;margin:0 0 8px">आपने जो बताया उसके आधार पर, यह है आपके business के लिए हमारा अगला step.</p>
    <h1 style="font-size:clamp(30px,4.6vw,48px)">
      @if ($lead?->firstName())
        Hi {{ $lead->firstName() }}, यहाँ है आपके लिए हमारी <em>recommendation</em>
      @else
        यहाँ है आपके business के लिए हमारी <em>recommendation</em>
      @endif
    </h1>
    <p style="font-size:18px;color:#475467;max-width:680px;margin:0 0 10px">Recommended next step:</p>
    <p style="font-size:26px;font-weight:900;color:var(--navy);margin:0 0 18px">{{ $recommendation->headline }}</p>
    <p style="color:var(--muted);max-width:680px;margin:0 0 28px">{{ $recommendation->positioning }}</p>
    <a class="primary" href="{{ route($recommendation->offerKey->routeName(), array_filter(['lead' => $lead?->id])) }}">{{ $recommendation->cta() }}</a>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div class="center" style="margin-bottom:10px"><div class="kicker">Your Goal</div><h2>जो आपने हमें बताया</h2></div>
    <div class="badge-row" style="max-width:520px;margin:0 auto 0">
      <div class="badge">
        <small>Goal</small>
        <strong>{{ $recommendation->goal->label() }}</strong>
      </div>
      <div class="badge">
        <small>Budget</small>
        <strong>{{ $recommendation->budget->label() }}</strong>
      </div>
    </div>
  </div>
</section>

<section class="section soft">
  <div class="container">
    <div class="center">
      <div class="kicker">Why we recommend this</div>
      <h2>{{ $recommendation->recommendationName }}</h2>
    </div>
    <div class="card" style="max-width:760px;margin:30px auto 0;background:#fff">
      <p style="margin:0;color:var(--ink);font-size:15px;line-height:1.7">{{ $recommendation->explanation }}</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container offer">
    <div class="offer-copy">
      <div class="kicker">Recommended offer</div>
      <h2>{{ $recommendation->offerName() }}</h2>
      <p>{{ $recommendation->deliverable() }} — इस budget और goal के लिए सबसे सही पहला step.</p>
      <ul class="offer-list">
        <li>{{ $recommendation->deliverable() }}</li>
        <li>Priority issues / opportunities की पूरी list</li>
        <li>Recommended next steps</li>
      </ul>
    </div>
    <div class="buybox">
      <div class="buybox-head">
        <div><h3>{{ $recommendation->offerKey->shortLabel() }}</h3></div>
        <span class="tag">{{ ucfirst($recommendation->salesIntent) }}</span>
      </div>
      <div class="price">₹{{ $recommendation->priceRupees() }}</div>
      <a class="primary buybtn" href="{{ route($recommendation->offerKey->routeName(), array_filter(['lead' => $lead?->id])) }}">{{ $recommendation->cta() }}</a>
      <div class="secure">🔒 Secure payment · Razorpay</div>
    </div>
  </div>
</section>

<section class="section soft">
  <div class="container">
    <div class="center">
      <div class="kicker">What happens next</div>
      <h2>Payment से लेकर recommendations तक</h2>
    </div>
    <div class="steps">
      <div class="step"><div class="num">STEP 1</div><h3>Submit your details</h3><p>Payment के साथ अपनी business details share करें.</p></div>
      <div class="step"><div class="num">STEP 2</div><h3>Complete payment</h3><p>₹{{ $recommendation->priceRupees() }} का secure online payment.</p></div>
      <div class="step"><div class="num">STEP 3</div><h3>हमारी team review करती है</h3><p>आपकी digital presence का पूरा review.</p></div>
      <div class="step"><div class="num">STEP 4</div><h3>Audit/Strategy मिलता है</h3><p>{{ $recommendation->deliverable() }} के साथ.</p></div>
      <div class="step"><div class="num">STEP 5</div><h3>Recommended next steps</h3><p>आगे क्या करना चाहिए, उसकी clear direction.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container trust">
    <div>
      <div class="kicker">Why Niranjan Enterprises</div>
      <h2>Maharashtra के business owners का <em style="color:var(--red);font-style:normal">trusted</em> digital partner</h2>
      <p style="color:var(--muted)">12+ साल का real, hands-on digital experience — sirf theory नहीं.</p>
    </div>
    <div class="statgrid">
      <div class="stat"><strong>66+</strong><span>Maharashtra businesses served</span></div>
      <div class="stat"><strong>12+</strong><span>years of digital experience</span></div>
      <div class="stat"><strong>4.9</strong><span>Google rating</span></div>
    </div>
  </div>
</section>

<section class="final">
  <div class="container center">
    <h2>{{ $recommendation->recommendationName }} से शुरू कीजिये</h2>
    <p>{{ $recommendation->positioning }}</p>
    <a class="primary" href="{{ route($recommendation->offerKey->routeName(), array_filter(['lead' => $lead?->id])) }}">{{ $recommendation->cta() }}</a>
  </div>
</section>

<footer class="footer">
  <div class="container">© {{ date('Y') }} Niranjan Enterprises Digital Solutions. All rights reserved.</div>
</footer>

</body>
</html>
