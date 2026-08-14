<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Your Special Offer — Visibility Audit | Niranjan Enterprises Digital Solutions</title>
        <meta name="description" content="Get a professional Google Business Profile or Website Visibility Audit from Niranjan Enterprises Digital Solutions — a limited-time offer at ₹120 / ₹240.">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-white">

        {{-- Header — deliberately no site navigation. This is a single-purpose
             landing page; the only action on it should be choosing an audit. --}}
        <header class="border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-center sm:justify-start">
                <a href="https://niranjanenterprises.com" target="_blank" rel="noopener noreferrer">
                    <img src="{{ asset('images/neds-logo.png') }}" alt="Niranjan Enterprises Digital Solutions" style="height:36px;width:auto">
                </a>
            </div>
        </header>

        {{-- Hero --}}
        <section class="bg-gray-50 border-b border-gray-100">
            <div class="max-w-3xl mx-auto px-4 py-14 text-center">
                <span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full mb-4">
                    Your Special Offer
                </span>
                <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 leading-tight">
                    Find Out What's Costing You Customers Online
                </h1>
                <p class="text-gray-600 text-lg mt-4 leading-relaxed">
                    Most businesses have no idea how they actually look to a customer searching for them on Google.
                    A visibility audit shows you exactly what's working, what isn't, and what to fix first — for
                    your Google Business Profile, your website, or both.
                </p>
                <div class="mt-8">
                    <a href="#pricing"
                       class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                        See Pricing &amp; Get Your Audit
                    </a>
                </div>
            </div>
        </section>

        {{-- What's checked --}}
        <section class="max-w-5xl mx-auto px-4 py-14">
            <div class="grid sm:grid-cols-2 gap-8">
                <div class="border border-gray-200 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Google Business Profile (GBP) Audit</h2>
                    <ul class="space-y-2 text-gray-600 text-sm">
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Profile completeness &amp; accuracy check</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Category, services &amp; business info review</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Photos, posts &amp; Q&amp;A audit</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Reviews &amp; rating overview</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Local search visibility snapshot</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Written report with prioritized fixes</li>
                    </ul>
                </div>
                <div class="border border-gray-200 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Website Audit</h2>
                    <ul class="space-y-2 text-gray-600 text-sm">
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> On-page SEO health check</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Page speed &amp; mobile-friendliness review</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Google search visibility check</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Content &amp; metadata review</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Technical issues (broken links, missing tags, etc.)</li>
                        <li class="flex gap-2"><span class="text-blue-600">✓</span> Written report with prioritized fixes</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section class="max-w-5xl mx-auto px-4 pb-14">
            <div class="grid sm:grid-cols-3 gap-8 text-center">
                <div>
                    <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-semibold flex items-center justify-center mx-auto mb-3">1</div>
                    <h3 class="font-semibold text-gray-900 mb-1">Pick your audit</h3>
                    <p class="text-gray-500 text-sm">Choose GBP, Website, or both — whatever you need reviewed.</p>
                </div>
                <div>
                    <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-semibold flex items-center justify-center mx-auto mb-3">2</div>
                    <h3 class="font-semibold text-gray-900 mb-1">Pay securely</h3>
                    <p class="text-gray-500 text-sm">Quick checkout via Razorpay — UPI, card, or netbanking.</p>
                </div>
                <div>
                    <div class="w-9 h-9 rounded-full bg-blue-600 text-white font-semibold flex items-center justify-center mx-auto mb-3">3</div>
                    <h3 class="font-semibold text-gray-900 mb-1">Get your report</h3>
                    <p class="text-gray-500 text-sm">Our team reviews your details and sends a written report with prioritized fixes.</p>
                </div>
            </div>
        </section>

        {{-- Pricing / CTAs --}}
        <section id="pricing" class="bg-gray-50 border-y border-gray-100 scroll-mt-6">
            <div class="max-w-5xl mx-auto px-4 py-14">
                <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Choose Your Audit</h2>
                <p class="text-gray-500 text-center mb-10">Introductory pricing — regular price shown for comparison.</p>

                <div class="grid sm:grid-cols-3 gap-6">
                    {{-- GBP Audit --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 flex flex-col">
                        <h3 class="font-semibold text-gray-900">GBP Audit</h3>
                        <p class="text-gray-500 text-sm mt-1 mb-4">Google Business Profile</p>
                        <div class="mb-4">
                            <span class="text-3xl font-bold text-gray-900">₹120</span>
                            <span class="text-gray-400 line-through ml-2">₹3,000</span>
                        </div>
                        @if ($gbpPaymentUrl)
                            <a href="{{ $gbpPaymentUrl }}"
                               class="mt-auto inline-block text-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Get My GBP Audit
                            </a>
                        @else
                            <span class="mt-auto inline-block text-center px-4 py-3 bg-gray-100 text-gray-400 text-sm font-medium rounded-lg">
                                Coming soon
                            </span>
                        @endif
                    </div>

                    {{-- Website Audit --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 flex flex-col">
                        <h3 class="font-semibold text-gray-900">Website Audit</h3>
                        <p class="text-gray-500 text-sm mt-1 mb-4">Full site review</p>
                        <div class="mb-4">
                            <span class="text-3xl font-bold text-gray-900">₹240</span>
                            <span class="text-gray-400 line-through ml-2">₹6,000</span>
                        </div>
                        @if ($websitePaymentUrl)
                            <a href="{{ $websitePaymentUrl }}"
                               class="mt-auto inline-block text-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Get My Website Audit
                            </a>
                        @else
                            <span class="mt-auto inline-block text-center px-4 py-3 bg-gray-100 text-gray-400 text-sm font-medium rounded-lg">
                                Coming soon
                            </span>
                        @endif
                    </div>

                    {{-- Both --}}
                    <div class="bg-white border-2 border-blue-600 rounded-xl p-6 flex flex-col relative">
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded-full">
                            Best Value
                        </span>
                        <h3 class="font-semibold text-gray-900">Both Audits</h3>
                        <p class="text-gray-500 text-sm mt-1 mb-4">GBP + Website</p>
                        <div class="mb-4">
                            <span class="text-3xl font-bold text-gray-900">₹360</span>
                            <span class="text-gray-400 line-through ml-2">₹9,000</span>
                        </div>
                        @if ($bothPaymentUrl)
                            <a href="{{ $bothPaymentUrl }}"
                               class="mt-auto inline-block text-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Get Both Audits
                            </a>
                        @else
                            <span class="mt-auto inline-block text-center px-4 py-3 bg-gray-100 text-gray-400 text-sm font-medium rounded-lg">
                                Coming soon
                            </span>
                        @endif
                    </div>
                </div>

                <p class="text-center text-gray-500 text-sm mt-8">
                    Secure payment via Razorpay &middot; GST invoice included &middot; Priced in INR
                </p>
            </div>
        </section>

        {{-- About / trust --}}
        <section class="max-w-3xl mx-auto px-4 py-14 text-center">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">About Niranjan Enterprises Digital Solutions</h2>
            <p class="text-gray-600 leading-relaxed">
                Niranjan Enterprises Digital Solutions is a GST-registered digital solutions agency based in Pune,
                Maharashtra. We help businesses grow online through SEO, Google Business Profile management,
                website design &amp; development, social media management, performance marketing, software
                development, and AI automation.
            </p>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-gray-100">
            <div class="max-w-5xl mx-auto px-4 py-8 text-center text-xs text-gray-400 space-y-1">
                <p>Niranjan Enterprises Digital Solutions</p>
                <p>2nd Floor, 657, Rangrekha Apartment, Near Kunjir Talim, Sadashiv Peth, Pune 411030</p>
                <p>
                    <a href="mailto:contact@niranjanenterprises.com" class="hover:text-gray-600">contact@niranjanenterprises.com</a>
                    &middot;
                    <a href="tel:+919220518202" class="hover:text-gray-600">+91 92205 18202</a>
                    &middot;
                    <a href="https://niranjanenterprises.com" target="_blank" rel="noopener noreferrer" class="hover:text-gray-600">niranjanenterprises.com</a>
                </p>
            </div>
        </footer>
    </body>
</html>
