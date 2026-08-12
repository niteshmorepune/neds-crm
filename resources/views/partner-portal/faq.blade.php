<x-partner-portal-app-layout title="FAQ" header="Frequently Asked Questions">
    <style>
        .partner-faq-content { color: #374151; font-size: 14px; line-height: 1.65; }
        .partner-faq-content h1 { font-size: 1.4rem; font-weight: 700; color: #111827; margin: 0 0 .75rem; }
        .partner-faq-content h2 { font-size: 1.1rem; font-weight: 600; color: #4f46e5; margin: 1.6rem 0 .6rem; }
        .partner-faq-content p { margin: .6rem 0; }
        .partner-faq-content strong { color: #111827; }
        .partner-faq-content a { color: #4f46e5; text-decoration: underline; }
    </style>

    <div class="partner-faq-content rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100 sm:p-8">
        {!! $html !!}
    </div>
</x-partner-portal-app-layout>
