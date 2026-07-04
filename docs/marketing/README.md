# NEDS CRM — Sales & Marketing Pitch Deck

A 14-slide PDF briefing for NEDS's sales/marketing team, covering:

1. Cover
2. Why the CRM exists (our own before/after)
3. The two ways to pitch it — custom software build vs. licensing the CRM itself
4. Product tour (all 8 modules)
5. GST/India-specific compliance
6. AI features
7. Integration ecosystem (Drishti, SMDost, WhatsApp)
8. Security & reliability
9. Proof points (real production stats)
10. Deployment cost / TCO
11. Ideal customer profile
12. Engagement models (framework — pricing intentionally left blank, confirm with management)
13. Objection handling quick reference
14. Call to action / contact

Output: [`pdf/neds-crm-pitch-deck.pdf`](pdf/neds-crm-pitch-deck.pdf) — landscape A4, print-ready.

## Regenerating

After editing `pitch-deck.html` (needs Node + a Chromium-based browser such as
Chrome or Edge installed):

```
npm run pitch-deck
```

Override the browser with `CHROME_BIN=/path/to/chrome` if it isn't auto-detected.

## Notes for whoever edits this next

- Content should stay in sync with reality — update slide 9 (proof points) and
  slide 4 (product tour) whenever a major module ships or changes.
- Slide 12 deliberately has no pricing numbers filled in — that's a business
  decision, not an oversight. Fill in before using with an external prospect.
- Each `.slide` div is one landscape A4 page (`page-break-after: always`).
