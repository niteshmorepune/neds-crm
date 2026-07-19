# Meta Lead Ads Playbook — NEDS's own lead generation

A ready-to-use campaign structure and ad copy for running Facebook/Instagram
Lead Ads to generate leads for NEDS's own services, plus how to read the
results once they land in the CRM. Written 2026-07-19, after the CRM's Meta
Lead Ads integration (webhook + auto-scoring) had been live and verified
since 2026-07-09. See [`docs/user-guides/admin.md`](user-guides/admin.md)
Section 8 for the technical webhook setup, and
[`docs/user-guides/integrations.md`](user-guides/integrations.md) for how a
lead is processed once it arrives.

## Before you launch — a 2-minute checklist

- [ ] Privacy policy link set in Meta App Settings → Basic:
      `https://niranjanenterprises.com/privacy-policy/` (already verified live)
- [ ] `META_APP_SECRET`, `META_WEBHOOK_VERIFY_TOKEN`, `META_PAGE_ACCESS_TOKEN`
      all set in the server `.env` (already configured — see admin guide
      Section 8 if this ever needs re-registering)
- [ ] App is in **Live** mode, not Development (required for real, non-test
      webhook delivery)
- [ ] Decide which 1–2 service clusters to launch first — don't run all four
      below at once on a tight budget; start with whichever has your
      strongest current proof (best case studies, fastest turnaround) and
      expand once real cost-per-Won-client data comes back from the CRM

## Campaign strategy

**Don't run one generic "digital marketing" ad.** NEDS's 8 service lines
have genuinely different buyers — a GMB/local-visibility buyer looks
nothing like an AI Automation buyer. Run separate ad sets per cluster:

1. **Local Visibility** — SEO + GMB (small shop/clinic/business owners who
   feel invisible on Google)
2. **Website Design & Development** — businesses with no site or an
   outdated one
3. **Performance Marketing** — businesses already spending on ads without
   seeing ROI
4. **Software Development / AI Automation** — higher-ticket, longer
   consideration; needs a more specific angle than a punchy hook

**Objective:** Leads → Instant Forms (the only lead type the CRM's webhook
listens for — the `leadgen` field). **Form type: "Higher intent,"** not
"More volume" — the extra review screen before submit meaningfully cuts
fat-finger/junk submissions for a small increase in cost per lead, which
matters more for a services business than raw volume.

**Targeting:** Maharashtra, weighted toward Pune and nearby — that's where
your service delivery and trust signal (physical presence) actually holds
up. Start broad + Advantage+ audience rather than narrow manual interest
stacking; there's no historical pixel/conversion data yet to target
against, and Meta's delivery algorithm optimizes fine on the in-platform
lead-submit event alone for basic volume/quality balance.

**Budget:** ₹500–1,000/day per ad set to start, 3–4 creative variations,
run each at least 4–7 days before judging — Meta's learning phase needs
real volume (roughly 50 conversions/week/ad set) before performance data
means anything.

## The Instant Form — same structure for all four campaigns

Only the "Which service" options change per cluster; everything else is
identical across all four ads below.

**Form type:** Higher intent (review screen before submit)

**Intro headline:** "Get your free [audit/consultation] from NEDS"
**Intro description:** "Takes under a minute. A real person from our team
will reach out within 1 business day — this isn't a mailing list."

**Questions, in order:**
1. Full Name *(standard, autofill)*
2. Phone Number *(standard, autofill)*
3. Email *(standard, autofill)*
4. Company Name *(standard, autofill)*
5. **"Which service are you looking for?"** — multiple choice, options must
   be the **exact active Service names** (case-insensitive match, but the
   same words) so the CRM's `ImportMetaLead` job can tag the lead's service
   automatically:
   - Local Visibility ad → `SEO`, `GMB`
   - Website ad → `Website Design & Development`
   - Performance Marketing ad → `Performance Marketing`
   - Software/AI ad → `Software Development`, `AI Automation`
6. **"What's your approximate monthly budget for this?"** — multiple
   choice; the question text must contain the word **"budget"** for the
   CRM's budget parser to touch it:
   - Under ₹10,000
   - ₹10,000 – 25,000
   - ₹25,000 – 50,000
   - ₹50,000+

Get these two questions right and a Meta lead scores exactly as well as a
rep manually entering one — right now that's the difference between a lead
sitting unscored vs. auto-flagging 🔥 Hot the moment it lands.

**Privacy policy:** `https://niranjanenterprises.com/privacy-policy/`

**Thank-you screen:**
> Headline: "Thanks — we've got it!"
> Description: "Someone from our team will call or WhatsApp you within 1
> business day. In the meantime, check out our work at
> niranjanenterprises.com."
> Button: View Website

---

## Campaign 1 — Local Visibility (SEO + GMB)

**Campaign/ad name in Meta:** `Local-Visibility-Pune-July2026-V1` — this
exact name becomes the CRM's Campaign value in the Lead Source Performance
report, so keep it recognizable.

**Creative direction:** Before/after screenshot of a Google Maps pack or
search ranking, or a simple stat-card graphic. Bilingual copy performs
better here than English-only.

**Primary text (Variant A):**
> Google पर आपका business नहीं दिखता? आपके competitors दिख रहे हैं, आप नहीं।
> We've helped 66+ Maharashtra businesses show up on top of Google Search
> and Maps. Free audit, no obligation — takes 2 minutes to request.

**Primary text (Variant B):**
> Your customers are searching for you on Google right now. Are you
> showing up — or is your competitor?
> Get a free visibility audit from a Pune-based team that's done this for
> 66+ local businesses.

**Headline:** Free Google Visibility Audit
**Description:** Rank higher. Get found. Get calls.
**CTA button:** Get Quote

---

## Campaign 2 — Website Design & Development

**Campaign/ad name:** `Website-Dev-Pune-July2026-V1`

**Creative direction:** Split-screen "outdated site vs. modern site"
mockup, or a short video of a site loading fast on mobile.

**Primary text (Variant A):**
> Still sending customers to a website that looks like it's from 2015? Or
> worse — no website at all?
> We design and build fast, mobile-first websites for Maharashtra
> businesses. Tell us about your business, we'll tell you what it'll take.

**Primary text (Variant B):**
> First impressions happen on your website before they happen in your
> shop. Make it count.
> Get a free consultation on what a modern website could do for your
> business.

**Headline:** Get a Modern Website Built
**Description:** Fast, mobile-first, built to convert
**CTA button:** Learn More

---

## Campaign 3 — Performance Marketing

**Campaign/ad name:** `Perf-Marketing-Pune-July2026-V1`

**Creative direction:** A simple "ROI up, cost down" style graph/stat
visual — this audience responds to numbers, not lifestyle imagery.

**Primary text (Variant A):**
> Already running Facebook or Google ads but not sure they're actually
> working?
> We audit your current ad spend for free and show you exactly where it's
> leaking — no long-term contract required to find out.

**Primary text (Variant B):**
> Spending on ads without knowing your real cost-per-customer is just
> guessing.
> Free ad performance audit — see what's working, what's wasted, and what
> to fix first.

**Headline:** Free Ad Spend Audit
**Description:** Know your real cost per customer
**CTA button:** Get Quote

---

## Campaign 4 — Software Development / AI Automation

**Campaign/ad name:** `SoftwareAI-Pune-July2026-V1`

**Creative direction:** This is the highest-consideration, lowest-volume
cluster — a longer-form, more specific angle works better than a punchy
hook. A short "problem we solved" case-study graphic if you have one.

**Primary text (Variant A):**
> Still tracking leads in Excel, or doing the same manual task every day
> that software could just... do?
> We build custom software and AI automation for businesses that have
> outgrown spreadsheets. Tell us what's eating your time — we'll tell you
> if it's automatable.

**Primary text (Variant B):**
> Not every business problem needs off-the-shelf software. Some need
> something built for exactly how you work.
> Free consultation — describe your process, we'll tell you what's worth
> automating.

**Headline:** Free Automation Consultation
**Description:** Custom software, built for you
**CTA button:** Contact Us

---

## After launch — how to actually judge performance

Two different questions, two different tools. Don't judge on cost-per-lead
alone — a ₹150 lead that never converts is worse than a ₹400 lead that
closes.

| Question | Where to look |
|---|---|
| Did people click and submit? | Meta Ads Manager — CTR, cost-per-lead, form completion rate |
| Did those leads become real business? | CRM: **Reports → Lead Source Performance**, filtered to Meta Ads — leads captured, conversion rate, **Avg AI score** (a quality proxy before a human even calls), and **Won value** |

**Weekly reconciliation:** pull Meta's per-ad CPL next to the CRM's
per-campaign conversion rate + Won value (the campaign name you set above
is what shows up in that CRM table now, not a raw numeric ID) to compute
the number that actually matters: **cost-per-Won-client per creative/
audience.**

**A later, more advanced step worth considering once volume builds up:**
feed the CRM's own quality signal (AI score, or whether a lead reached
Won) back to Meta via the Conversions API as a custom "Qualified Lead"
event, so Meta's delivery algorithm starts optimizing for lead *quality*
rather than pure volume. Not built yet — revisit once there's a real
season of Meta lead data in the CRM to judge whether the extra
integration work is worth it.

## Notes

- Every headline is kept under Meta's 40-character limit and primary text
  short enough to show fully without "See more" on mobile — double-check
  in Ads Manager's own preview once pasted in, since exact truncation
  varies by placement.
- "66+ Maharashtra businesses" is a real number pulled from the CRM
  (active clients), not a placeholder — keep it updated if this copy gets
  reused months from now.
