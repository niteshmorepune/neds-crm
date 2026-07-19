# NEDS CRM — Marketing & Explainer Documents

Three branded PDFs, all built from HTML in this folder via the same headless-Chrome
pipeline. All share the same landscape-A4 "slide" design system (see any `.html`
file's `<style>` block).

## 1. Sales & Marketing Pitch Deck (`pitch-deck.html`)

Internal, angle-based sales deck for the NEDS sales/marketing team — **not** meant
to be handed to a prospect as-is (it's written as talking points/scripts). 14 slides:
cover, why the CRM exists, the two ways to pitch it (custom build vs. licensing),
product tour, GST compliance, AI features, integration ecosystem, security, proof
points, TCO, ideal customer profile, engagement models, objection handling, CTA.

Output: [`pdf/neds-crm-pitch-deck.pdf`](pdf/neds-crm-pitch-deck.pdf).

## 2. The Complete Guide (`explainer-guide.html`)

External-facing FAQ/explainer — the document to hand directly to a prospect, client,
new teammate, or partner who asks "what is this and why did you build it yourselves?"
9 slides: one-paragraph summary, honest off-the-shelf-vs-custom comparison, **when
off-the-shelf still wins** (deliberately included for credibility), real adoption
numbers pulled from production (not inflated), audience-specific quick answers
(prospect/client/teammate/partner), objection Q&A, CTA.

Output: [`pdf/neds-crm-explainer-guide.pdf`](pdf/neds-crm-explainer-guide.pdf).

**Keeping the adoption numbers honest:** slide 5 quotes real production counts.
Refresh them before resending to anyone if it's been a while — pull via SSH:
```
php artisan tinker --execute="
use App\Models\{Invoice,Payment,Quotation,Customer,Deal,Ticket};
echo 'invoices: '.Invoice::count().PHP_EOL;
echo 'payments: '.Payment::count().PHP_EOL;
echo 'quotations: '.Quotation::count().PHP_EOL;
echo 'active customers: '.Customer::where('status','active')->count().PHP_EOL;
echo 'deals: '.Deal::count().', tickets: '.Ticket::count().PHP_EOL;
"
```

## 3. Tours & Travel — AI Solution (`travel-vertical-pitch.html`)

A worked example for a specific vertical (prompted by a real prospect conversation
about a tours & travel business) — shows what's reused as-is from the proven CRM
foundation (GST engine, portal, AI discipline, low hosting cost) versus what would
be built fresh for travel (itinerary/package quoting, tour-operator GST treatment,
group bookings, payment schedules tied to travel date, vendor ledger).

Output: [`pdf/neds-tours-travel-ai-solution.pdf`](pdf/neds-tours-travel-ai-solution.pdf).

This is the template for any future vertical one-pager — copy its structure
(why generic doesn't fit → workflow diagram → reused-vs-new honesty slide →
why-us proof → CTA) for the next industry a prospect asks about.

## Regenerating

Needs Node + a Chromium-based browser (Chrome or Edge) installed:

```
npm run marketing-pdfs
```

Override the browser with `CHROME_BIN=/path/to/chrome` if it isn't auto-detected.
Regenerates all three PDFs in one run.

## Notes for whoever edits this next

- Keep the pitch deck's proof points (slide 9) and the explainer guide's adoption
  numbers (slide 5) in sync with reality — update whenever a major module ships or
  the team's usage habits meaningfully change.
- Pitch deck slide 12 (engagement models) deliberately has no pricing filled in —
  a business decision, not an oversight. Fill in before using with an external
  prospect.
- The explainer guide's "when off-the-shelf still wins" slide is intentional —
  don't remove it to make the pitch stronger; it's what makes the rest credible.
- Each `.slide` div is one landscape A4 page (`page-break-after: always`).
