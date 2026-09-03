# Sales guide

Your workflow runs **Lead → Deal → Quotation → Invoice**. Here's how each step
works in the CRM.

## Your dashboard
When you log in you'll see:
- **Follow-ups due** — leads you need to chase today.
- **Won this month** — value of deals you've closed.
- **Open pipeline by stage** — your live deals and their value.
- **Overdue tasks alert** — if you have tasks past their due date, a red banner
  appears with a direct link to your task list.
- **Your Productivity This Month** — your rank among other Sales staff this
  month (e.g. "#2 of 5"), an overall score, and your single biggest
  opportunity area (e.g. "leads converted"). Private to you — nobody else
  sees your rank, and you don't see anyone else's. If AI is enabled, click
  **Get tips to improve** for a short, specific suggestion based on your own
  numbers. Shows "Not enough peers yet to compare" if there aren't at least
  2 Sales staff yet.

## Resources
**Sidebar → Resources** has two tabs — **Files** (a shared internal file
library) and **Links** (a company-wide reference list). You can view
whatever your role can see on both; adding, editing, or removing entries is
Admin/Manager only. The **Links** tab is worth checking before you improvise
over WhatsApp: it often has the exact link you need to hand a client or lead
— tagged **Client Signup Link** (e.g. a hosting or payment-gateway signup
link when a client wants to buy one) or **Scheduling Link** (e.g. "Connect
with Sales Team" to book a call) — rather than you tracking one down or
copy-pasting an old one from memory. Some items are restricted to specific
roles ("Visible to") — if something you expect isn't there, it may simply
not be tagged for Sales; ask Admin/Manager to add or open it up.

Every **client's own page** also has its own **Links** tab, separate from
this — that client's website, Google Business Profile, socials, Drive
folder, and similar. Anyone who can open the client's page can see these.

## 1. Leads (Lead Generation)
You only see leads you own (or that are unowned) here — not another Sales
rep's leads (fixed 2026-09-03; this used to show everyone's).

The top of the page shows **summary cards** — Total, New, Contacted,
Qualified, Converted, Lost — a quick read on your pipeline before you
scroll the list. They reflect your leads regardless of any filter you've
applied to the list below, so they stay a stable overview. **Each card is
clickable** — click any number to jump straight to the list filtered to
that status (click **Total** to clear the status filter and see all of
yours). The **Converted** card also shows a small **"X Won"** line
underneath — Converted only ever means the lead became a real Deal, not
that it's a closed sale, so this tells you at a glance how many of those
Converted leads have actually been won without opening each one. The list
itself has a **Latest Note** column showing each lead's most recent note
(hover over a truncated one to read the full text) — no need to open a
lead just to check what was last said.

**What to work first:** the list sorts by **Priority** by default, not
newest — an overdue follow-up always comes first, then a follow-up due
today, then a Hot lead nobody's followed up on yet, then everything else.
Switch to **Newest** (toggle above the list) if you want plain
chronological order instead. A red **Overdue** or amber **Due today**
badge shows right on a lead's row when relevant. Above the list, the
**"Needs your attention today"** strip gives you five one-click counts —
overdue, due today, Hot-but-untouched, **unresponsive** (3 or more
call/WhatsApp attempts with no answer and no reply, on a lead that's
still open), and **status may need updating** (see below) — scoped to
your own leads. Open an unresponsive lead itself and you'll see a
**"📵 Not responding — next best action"** box telling you exactly what to
try: switch channels if you've only tried calls (or only WhatsApp) so far,
including the historically best time to call when there's enough data;
once both channels have genuinely been tried with nothing back, it points
you to the status suggestion below instead of leaving you guessing.

**Keep "New" honest — the "This was a call" shortcut:** a lead only
counts as genuinely untouched while it's **New**. The moment you log any
real outreach against it — a call via **Log a call**, or a note where you
tick **📞 This was a call** and pick an outcome right there in the note
box — it auto-promotes to **Contacted**, no separate status edit needed.
If you type a plain note describing a call without ticking that box, the
lead stays New with real activity against it — a purple **✏️ Status may
need updating** note appears under its Status badge (and it's counted in
the strip above), so it doesn't silently look untouched to you or anyone
scanning the New tile.

**Fixing an already-flagged lead:** open the lead itself — a flagged lead
(New with real activity, OR unresponsive after 3+ real attempts on both
channels) shows a **✨ Suggest a status** button. Click it and, if AI is
enabled, Claude reads the lead's own notes and calls and suggests
Contacted, Qualified, or Lost with a one-line reason — e.g. "they asked
for a formal quote" → Qualified, or "no response on either channel after
several real attempts" → Lost for a genuinely dead unresponsive lead.
Nothing changes automatically — pick the status from the dropdown (the
AI's pick is pre-selected but you can choose a different one) and click
**Apply**. If AI can't suggest anything, or is off, you still get the
same dropdown to pick from yourself.

**A Converted lead that isn't Won yet:** once a lead converts, its Status
column shows **Converted** plus a small caption underneath describing
what actually happened to the Deal — **"Deal: Negotiation"**, **"Deal:
Proposal"** (gray), **"Deal: Won"** or **"Deal: Won (partial payment)"**
(green), or **"Deal: Lost"** (red). Use the **deal stage** filter (next to
the other filters above the list) to pull up exactly which of your
Converted leads are stuck at a given stage — e.g. filter to Negotiation to
see every one that needs a push to actually close. A lead's status never
reverts once it's Converted, even if the resulting Deal is later Lost —
that's intentional, it keeps the historical record of "this became a real
opportunity" separate from how that opportunity turned out.

**If you leave a brand-new lead untouched:** the CRM reminds you with a
bell notification about 20 minutes after a new lead lands with you, if you
haven't added a note, logged a call, or edited it yet. If it's still
untouched an hour in, your manager gets notified too — so it's worth a
quick note or call even just to log "left a voicemail" and clear it.

Arriving here from a **Lead Source Performance** report link (Manager
guide) automatically filters the list to that exact source and month, so
the number you clicked and the leads you land on always match.

1. **Lead Generation → Add Lead**.
2. Fill in name, company, phone/email, **source**, the **service** they're
   interested in, and an **estimated value**. Assign an **owner** (usually you).
   An optional **Alternate phone** field is there too, for a second number
   (e.g. WhatsApp vs. office line) — it's shown on the lead's page but isn't
   used for anything automatic (duplicate-detection only checks the main
   Phone field).
3. Save. A **score badge** (0–100) appears on the leads list within a minute —
   higher means more promising. Hover over it to see the one-line reason
   (e.g. "Company provided, specific service request, phone available — follow
   up promptly"). The score updates automatically whenever you edit the lead.
   The lead page also shows the AI's estimate of their **budget**, **urgency**,
   and whether the requested service looks like a good **fit** for them.

**Score ≥ 70 is a 🔥 Hot lead** — its owner gets an immediate bell notification
instead of waiting for the next morning digest, so you can call while the
interest is fresh.

> **Note:** leads that existed before AI was enabled show no badge until you
> open them, make any edit (even adding a note), and save.

**Auto-assignment:** if a lead comes in with no owner (e.g. from the website
form or an unmatched WhatsApp number), the CRM automatically assigns it to
whichever active Sales user currently has the fewest open leads — so leads
never sit unowned waiting for someone to notice them. This runs whether or
not AI is enabled. You'll get the usual "New lead" bell notification as soon
as you're assigned. An admin/manager-configured routing rule can override
this for a specific campaign or service (Admin guide) — you'll still get the
same "New lead" notification either way.

**Reassigning a lead (going on leave, rebalancing, or handing off a client):**
open a lead you own and click **Reassign** near the top. Pick another active
Sales team member and a reason (On leave / Left the company / Rebalancing
workload / Other), then confirm. The new owner gets a bell notification, and
a note is left on the lead recording who it moved from/to and why — visible
to anyone who opens it later. You can only hand a lead off to another Sales
peer this way, not to a Manager/Admin. If you're leaving the company
entirely, an admin can hand over **all** your open leads at once when they
deactivate your account (Admin guide, Section 9) — you don't need to
reassign each one yourself first.

**Campaign source:** if a website lead came in through a tracked ad or link,
the lead page shows a **Campaign** line (e.g. "google / cpc /
seo-pune-2026") so you know which channel it came from before you call.

**WhatsApp leads:** there are two WhatsApp numbers — a Marketing line for
pre-sale enquiries and a Support line for existing clients. A message on the
Marketing line creates or updates a **lead** (source = WhatsApp) only when
the phone number doesn't already belong to a client — the marketing number
is pre-sale by definition, so an unmatched number never gets silently
absorbed into Support. If the same number messages again before you've
converted them, the CRM adds it as a note on the same lead rather than
creating a duplicate. If the number already belongs to an existing client,
it's never turned into a lead — it's logged as a note on that client's own
record instead, so check there (not Lead Generation) if you're expecting to
see it.

**Replying to a lead over WhatsApp:** open the lead and use the note box —
if the lead has an open WhatsApp conversation, you'll see an **"Also send
as WhatsApp reply"** checkbox next to it. Leave it unchecked for a normal
internal note (the default); tick it and the note is also sent to the
client on WhatsApp, and gets a green "Sent via WhatsApp" badge once sent.
You don't need to open wadesk.in yourself to reply.

**Meta Ads leads:** a submission on a Facebook or Instagram lead ad creates a
lead automatically (source = Meta Ads). Any question on the ad form beyond
name/email/phone/company (e.g. a custom budget question) appears as a note
on the lead.

**Visibility Audit Funnel:** **Lead Generation → VA Recovery** shows the
whole journey for Meta Ads leads tagged the GMB service — how many came in,
how many were invited, how many viewed the offer page, reached checkout,
and paid — plus the same "who's stuck, at which stage" queue as before.
Meta's own lead form never sends anyone to the offer page on its own, so
the CRM sends a first WhatsApp invite automatically as soon as one of these
leads is created; if they stall after that, a separate recovery nudge goes
out 2–4 hours later, both "Stop promotions"-gated templates. Each queued
row links straight to that lead's own page so you can act with the usual
tools — a lead who converts from either automatic message simply
disappears off the list, nothing extra to do there. A row still stuck at
checkout or the offer page also gets a green **WhatsApp →** button
alongside "Open lead" — it jumps straight into that lead's own wadesk
chat with the matching recovery template already picked and filled in
(name, lead link); you just review and hit Send, no hunting for the
right conversation or the right template. That lead's own page
also shows a colored **"Visibility Audit:"** badge (e.g. "Reached
checkout, hasn't paid") right under its name, so you don't need to come
back to this list to remember why a lead needs a call.

This funnel stage also feeds the lead's **AI score** (and therefore its
place in the Priority sort, Section 1 above) — reaching checkout or paying
re-scores the lead immediately, since that's real buying intent even
before you've made contact. Merely being invited or eligible, with no
engagement yet, doesn't move the score either way.

**Once a lead pays ₹120 for the audit**, the rest of the journey is mostly
automatic, with two points where you take over:
1. The moment payment clears, the client gets an automatic thank-you on
   WhatsApp and email — nothing for you to do.
2. About 30 minutes later, they get a short "work has started" update on
   both channels too — again, automatic (it retries a few times if
   delivery fails, then stops and needs a manual nudge from you — check
   **Your message log** below if a client says they never got it).
3. Once the audit itself is actually ready, click **Mark audit ready** on
   the lead's **"Visibility Audit:"** badge — this notifies you (the
   lead's owner) to go ahead and schedule the 15-minute Gmeet walkthrough
   with the client, the same way you'd schedule any other meeting.
4. After you've held that call, upload the finished report file and click
   **Send Audit Report** — this emails the client the real file as an
   attachment and sends a WhatsApp message with a link to view it online,
   both from one click. The button only works once the Gmeet has actually
   happened — the report is never meant to go out before you've walked
   the client through it live.
5. From there it's the usual flow: **Send Quotation** (Section 3 below),
   which now also sends a WhatsApp message alongside the email, and the
   client can pay a milestone advance online themselves once it's sent.

Below that whole-team queue, two more sections show just **your own**
leads: **Your gaps** (your leads missing a service tag, stuck waiting on a
call, or who replied on WhatsApp with no response from you yet — each
with an "Open lead" link) and **Your message log** (every AI-WhatsApp send
to your own leads, with the outcome). If a lead needs a service tag,
tagging it (Edit → Service → GMB) is what actually turns the automated
invite on — it does nothing at all until that's set.

**Automatic nurture follow-ups:** if a New lead sits with no note or logged
call from you for **1, 3, or 7 days**, the CRM drafts a follow-up message for
you automatically — it appears as an "✨ AI-drafted follow-up (touch N/3)"
note on the lead, and you get a bell notification. Copy it into WhatsApp or
email (editing as needed) and send it yourself — nothing is ever sent
automatically. The moment you add your own note or log a call on a lead, the
sequence stops (the CRM assumes you've taken it from here).

**A lead the AI answered outside business hours also shows as follow-up
due, immediately.** The after-hours WhatsApp assistant only ever sends a
holding reply — it never books anything or closes a deal — so the moment
it replies to a lead with no follow-up already scheduled, the CRM marks
that lead as due for a real follow-up right away (no reminder was set
before this, and leads were quietly going a day or more without any
follow-up as a result). You'll see it as overdue the next time you check
your "follow-ups due" list — same list, no separate place to look.

**Working a lead:**
- Open the lead to add **notes**, see the timeline, and log activity.
- **Log a call** (top bar or the lead page) after you phone them — record the
  outcome (connected, no answer, follow-up needed). Logging a call against a
  brand-new lead automatically moves its status from **New** to
  **Contacted** — you don't need to also edit the lead separately just to
  flip that field. Logged a call by mistake (wrong lead, duplicate entry)?
  A **Delete** link appears next to it (Calls tab, lead page, or the
  Calling list) — only you or a manager/admin can delete it.
- On the Notes field, click **Dictate** and speak instead of typing — your
  browser transcribes it live into the box, and you can still edit before
  saving. (Chrome/Edge only; the button doesn't appear in browsers that
  don't support it.)
- If AI features are enabled, you'll also see **Record voice note
  (Hindi/Marathi/English)** below the Notes field — useful if it's faster to
  just speak the call recap than to type it. Unlike Dictate, this doesn't need
  you to speak in English: record in Hindi, Marathi, English, or a mix, and
  save the call as usual. A background job transcribes and translates it to
  English within about a minute; the translated note then appears under your
  typed notes on the **Calling** page (it shows "🎙️ Transcribing…" until
  it's ready). Your own typed notes always stay untouched — the voice
  transcript is a separate block, so review it and correct anything the
  transcription got wrong (names and numbers occasionally get misheard).
- When logging a call, click **Add follow-up reminder** to expand the reminder
  section. **Set the date and time manually** to the exact day you want to be
  reminded — the field starts blank so you pick the right date (not just
  tomorrow). Add a one-line next action (e.g. "Send proposal", "Call back at
  3 PM"). The CRM will send you a bell notification when that time arrives, and
  it appears in your morning digest. Call follow-ups that are overdue show in red
  on the **Calling** page — use the **Pending follow-ups** button to filter to
  just those. **You don't need to clear a reminder yourself once you've
  actually reached the person again** — logging a new call against the same
  client/lead with outcome **Connected** or **Follow-up Needed**
  automatically clears any earlier pending reminder for them, even if its
  date hasn't arrived yet. A **No Answer**/**Busy** attempt doesn't clear
  it, since you still haven't actually reached them.
- Set a **next follow-up date** on the lead itself so it shows up in your
  "follow-ups due" dashboard widget.
- **Draft follow-up (✨)** — click this button to have AI write a suggested
  follow-up message based on the lead's details and history. It ends with one
  genuine, specific discovery question about their requirement or pain point
  (their current setup, what isn't working, their goal, their timeline) —
  never a generic "let me know if you have questions" — unless the history
  already shows a real next step in motion (a quotation sent, a meeting
  booked), in which case it points to that instead. Read it, edit it to match
  your voice, then send it yourself (WhatsApp, email, or call). The AI never
  sends anything automatically.
- **Summarize (✨)** — click this button (above the notes list, next to Draft
  follow-up) to have AI condense a lead's whole notes timeline — including
  every WhatsApp message on it — into a few sentences. Handy for a lead
  that's had a long back-and-forth and you want the gist before your next
  call, without scrolling through everything.

**Merging duplicate leads:** if the same person or company ended up as two
leads (a duplicate WhatsApp enquiry, a repeat website form), tick exactly 2
on the **Lead Generation** list and click **Merge Selected**. On the review
screen, pick which record survives, then — per field (name, company, phone,
email, source, service, value, owner, status) — choose which of the two
leads' values to keep; you don't have to keep everything from the same one.
All notes, call logs, meetings, and activity history from the other lead
move onto the survivor, and a note is left recording what was merged in.
The other lead is then archived (soft-deleted, not gone — recoverable if
this was a mistake) so it stops cluttering the list.

**Converting a lead:** when it's real business, open the lead and click
**Convert**. This creates a **Client** and a **Deal** automatically and links
everything together. The new client appears in the **Clients** list with status
**Prospect** (shown in yellow) — they become **Active** (green) automatically
when you mark their deal as **Won**. Use the status filter on the Clients page
to view Prospects, Active, or all clients.

**Send Quotation from a lead:** you don't have to convert first. Open any lead
and click **Send Quotation**. If the lead isn't converted yet, the CRM converts
them automatically (creates the Client and Deal), then opens the Quotation
builder with the client and deal already filled in. If they're already a client,
it skips conversion and goes straight to the builder. Either way you land on a
blank quotation ready to fill in — no manual selection needed.

## 2. Sales Pipeline
The **Sales Pipeline** board shows your deals in columns by stage:
**New → Contacted → Proposal → Negotiation → Won / Lost**. It's a working
screen — just the board, so you can focus on moving deals forward. The full
numbers (KPI strip, stage conversion, trends, targets) live one click away
on **Sales Dashboard** — see 2a below.

- **Drag a deal** to the next column as it progresses (or open it and change the
  stage).
- **Dropping a deal on Lost** asks you to pick why — Price, Bad timing,
  Chose a competitor, Went dark / unresponsive, or Not a good fit. Pick one
  and the move completes; the same picker appears if you change the stage
  to Lost from the deal's own page instead. This is required — a deal
  can't move to Lost without a reason, so the team can actually see why
  deals are lost, not just how many.
- If AI is enabled, the picker briefly checks the deal's notes (and its
  original lead's notes/calls, if it came from one) and pre-highlights the
  option it thinks fits, with a one-line "why" underneath — e.g. "✨
  Mentioned choosing a rival agency in the last call." It's only ever a
  suggestion: pick a different one any time with zero extra steps, and a
  deal with too little history to go on just shows the plain picker with
  no pre-selection, never a guessed default.
- Won and Lost are final — once set, a deal's **stage** can't change again.
  Its **Value** can still be corrected afterwards, though (e.g. if the final
  amount was entered wrong) — every report, dashboard, and revenue figure
  picks up the corrected number automatically.
- A **won** deal can become a **Project** for the delivery team.
- On a **Client's** page, the **Deals** tab has Edit/Delete links for every
  deal, including Won ones — handy for fixing a value or removing a
  duplicate without leaving the client's profile.
- **Referred by** — if a deal came to NEDS through a partner agency rather than
  directly, open the deal and set the **Referred by** dropdown to that agency.
  Leave it as "Direct (no agency)" for clients who came to NEDS on their own.
  This lets management see which deals were agency-sourced vs direct.
- **Value (₹) is required** when adding or editing a deal — enter your best
  estimate even early on (New/Contacted) and correct it as the deal firms up.
  It drives every figure on the Sales Dashboard's KPI strip, so a missing or
  0 value understates your own numbers.
- **Enter the amount before GST** — not the quotation or invoice total. Once
  a quotation exists, use its Subtotal line, not the GST-inclusive Total at
  the bottom. Your Incentive slabs (see Section 5) are calculated directly
  off this number, so a GST-inclusive Value overstates your own sales for
  the month by the GST rate.
- **Deals like this one** — on a deal's page, a panel shows up to 3 other
  closed deals (Won or Lost) for the same service, ranked by how close their
  value is to this one — useful context on how deals with this profile tend
  to actually go. It needs a service set on the deal to show anything, and
  won't appear until this service has at least one other closed deal to
  compare against.
- **Draft follow-up (✨) and Summarize (✨)** — the same two AI buttons from
  the Lead Generation notes box (Section 1) are also on a deal's own Notes
  section, working from that deal's own notes (a deal has no call history
  of its own — only leads do). Handy once a lead has converted and the
  conversation keeps going as a deal instead.
- **If a deal goes quiet, AI drafts a check-in for you.** Any open deal with
  no note or edit for 7 days gets an AI-drafted check-in note automatically
  — a bell notification tells you it's ready. It's a staff-only draft, same
  as everywhere else: you review and send it yourself, nothing goes out on
  its own. This can happen more than once on the same deal — work it, go
  quiet again for a week, get another draft.

**Stale-deal badge** — each card shows how many days it's been sitting in its
current stage, turning red past 10 days. A red badge is a nudge to follow up
or move the deal, not an automatic penalty.

## 2a. Sales Dashboard
A dedicated page (Sidebar → **Sales Dashboard**, or the link at the top of
the Pipeline board) with the full numbers, scoped to your own deals (Admin/
Manager see everyone):

**KPI strip** — seven figures at the top:
- **Open pipeline** — total value of everything still open (not yet Won/Lost).
- **Weighted forecast** — open pipeline value adjusted by a rough
  likelihood-to-close per stage (New 10%, Contacted 25%, Proposal 50%,
  Negotiation 75%) — a more realistic number than raw pipeline value, since
  not everything in New will actually close.
- **Won this month** / **Won this FY** — value of deals you've won, this
  calendar month and this financial year (Apr–Mar) to date.
- **Win rate** — Won ÷ (Won + Lost), all-time.
- **Avg deal size** — average value of your Won deals.
- **Avg sales cycle** — average days from a deal's creation to it being won.

**Stage conversion** — below the KPI strip, what % of deals that ever reached
one stage went on to reach the next (New→Contacted, Contacted→Proposal,
Proposal→Negotiation, Negotiation→Won). This is built from deal moves going
forward only, so each pair shows "Not enough data yet" until at least 5 deals
have passed through it — it doesn't reconstruct history from before this
feature shipped.

- **Target vs actual** — a progress bar against your monthly (and FY, if set)
  revenue target, if Admin/Manager has set one for you. "No target set" is
  normal if they haven't.
- **Won value — last 12 months** — a trend chart, so you can see whether
  this month is actually better or worse than recent months, not just in
  isolation.
- **Service-line breakdown** — your pipeline and win rate per service (SEO,
  Website Dev, etc.), so you can see which services are actually converting.
- **Needs attention** — a plain list of deals that need a look: stale >10
  days, an overdue follow-up date, no owner, or a ₹0 value that was never
  corrected. Each links straight to the deal.

Admin/Manager additionally see a **rep leaderboard** (pipeline, won this
month, target %, win rate, avg deal size per Sales rep) and a **Save targets**
form to set the company's monthly/FY target and each rep's monthly target.

## 3. Quotations
1. **Quotations → Create** — or open a **Deal** and click **+ New Quotation**
   directly on the deal page (the client and deal are pre-filled for you) — or
   open a **Lead** and click **Send Quotation** (the CRM converts the lead and
   pre-fills the builder for you automatically).
2. Add **line items** (description, HSN/SAC, quantity, rate). GST is calculated
   per line (CGST+SGST for Maharashtra clients, IGST otherwise). If a
   quotation mixes one-time setup work with an ongoing monthly management fee
   (a common shape — e.g. a website build plus monthly ads management), tick
   **Recurring** on the line items that repeat every month. This doesn't
   change billing by itself — it's what lets **Create recurring invoice**
   (step 7 below) pre-fill correctly later.
   - **✨ Suggest line items** — when the quotation is linked to a deal that
     has notes, this button drafts a first pass at the description/quantity/
     SAC for each line, grounded only in what's actually in those notes
     (e.g. a note mentioning "client wants a Hindi translation" becomes its
     own line item). **It never fills in a rate or GST %** — those stay
     blank on every suggested line, exactly like a manually-added one, so
     you still price and save it yourself. If the deal has no notes yet,
     it says so rather than guessing.
3. Add a **Scope of Work** — the paragraph explaining what NEDS will deliver
   under this quotation, shown to the client above the line items.
   - **✨ Draft scope of work** — same idea as Suggest line items: when the
     quotation is linked to a deal that has notes, this button drafts the
     paragraph for you, grounded only in the deal's notes and service line.
     It never mentions price, rate, or GST — that's what the line items and
     totals are for — and it never saves on its own; it just fills the box,
     so review and edit before saving like any other field. If the deal has
     no notes yet, it says so rather than guessing.
4. For milestone/project work, add **milestones** (e.g. advance on signing,
   balance on delivery). Once work starts, whoever runs the project marks each
   milestone **Pending / In Progress / Done** on the quotation page — that's
   how accounts knows a phase is finished and it's time to raise the next
   invoice. The client can also **pay the next milestone online themselves**
   — the public quotation link (the one the WhatsApp/email send shares, see
   point 7 below) shows a "Pay ₹X now" button for whichever milestone is
   next due.
   Paying it automatically raises that milestone's invoice and records the
   payment — nobody on the team needs to do anything, and the milestone
   shows **Paid** on the quotation page (both yours and the client's) the
   moment it clears.
5. Save, then **download the PDF** to send to the client.
6. **New quotations need Admin/Manager approval before they can be sent** —
   every quotation you save starts **Pending approval**, and **Send to
   Client** stays greyed out until a manager approves it (they're notified
   automatically, and it also shows up on their **Approval Center** page —
   see the manager guide). You'll see one of three outcomes on the
   quotation page:
   - **Approved** — Send to Client unlocks, nothing else changes.
   - **Rejected**, with a note explaining why.
   - **Changes requested**, with a note on what to fix — edit the
     quotation, then click **Resubmit for approval** to send it back into
     the queue.
7. Open it and click **Send to Client** — this emails the quotation details to
   the client's billing address and marks it **Sent**. If the client has a
   phone number on file, it also sends a WhatsApp message with a link to
   view the quotation online — no extra step, both go out from the same
   click. It also drops a 3-day **follow-up reminder** onto your own
   Dashboard automatically (see Getting Started → Follow-up Reminders), so
   a quotation can't quietly fall through the cracks — if the client was
   referred by a partner agency, the reminder names the partner too (e.g.
   "Follow up with Prajakta Dahake (referring partner)...").
   - **Reseller partners:** if the client you picked was referred by a
     partner set up as a reseller (see Manager guide → Content
     collaboration → Reseller billing), you'll see an amber notice near
     the client field — the quotation is actually issued to the partner's
     own billing customer, not the client directly. This is expected for
     those specific partners; the client stays linked for your own
     tracking either way.
8. When the client agrees, mark the quotation **Accepted**, then **Convert to
   invoice** — accounts takes it from there.
   - If any line items were ticked **Recurring**, a **Create recurring
     invoice** button also appears on the accepted quotation. Click it to
     open a recurring invoice template pre-filled with the client and those
     recurring line items — pick a service, confirm the start date, and
     save. The quotation page then shows a link to the recurring template it
     produced, so the connection between what was quoted and what's now
     billing monthly stays visible from either side.

All quotations linked to a deal are listed on the deal's page so you can track
which version the client accepted.

## 4. Clients
**Clients** lists the companies assigned to you, plus any unassigned ones. Only
your clients and unassigned clients appear in your list — clients owned by other
sales reps are not shown. Open a client to see its contacts, notes, calls, deals,
invoices and tickets in one place.

The top of the list has clickable **Total Clients / Active / Prospect /
Inactive** cards, same one-click-filter idea as the Leads cards above.
**Prospect** is worth checking regularly — it's every client record that
exists because one of your leads converted, but whose Deal hasn't been
Won yet. In other words: your "on the way to becoming a real client" list,
in one place, separate from your already-Active clients.

Every active client's page opens with a **summary strip** — **MRR**, **Next
Renewal**, **Total Revenue**, and **Outstanding** (clickable, jumps to the
Invoices tab) — plus a **Health Score** badge (0–100) next to their status,
worst clients scoring lowest. If a client's score has dropped, that's your
cue to check what's changed before it becomes a bigger problem.

**Services tab — who's working on what:** the **Services** tab on a client's
page shows a **Team** column on the Recurring Services table (retainer
services like SEO/GMB with no project) — click **Assign** to pick who's
responsible. Below the tables, **Service Links** holds service-specific
URLs (Website URL, GBP link, social handles…). Two more tabs,
**Requirements** and **Assets**, track what a client still needs to send
per service (with due dates) and store their brand assets, website
content, and documents in a categorized library — uploading over an
existing file with **Replace** keeps the earlier version downloadable
instead of losing it.

**Adding clients:** use **Clients → Add** for a single client, or **Clients → Import**
to upload a CSV file in bulk. Download the template from the Import page — it
includes columns for address, owner (type a user's name exactly), and tags
(comma-separated, e.g. `seo, retainer`). If you leave the **Owner** column blank,
the client is assigned to you automatically. Click **Continue** and wait for
"Uploading…" to clear before the step advances — large files take a moment.

**Overseas clients (outside India):** set the **Country** field to the client's
country (e.g. "United States"). This hides the GSTIN and State fields (which
don't apply) and tells the system to produce zero-rated invoices for that client
— no GST is charged, and the PDF is labelled "Export of Services" automatically.
Leave Country as "India" for all domestic clients.

**Non-GST clients (domestic):** for a domestic client who has arranged to be
billed without GST, tick **Non-GST client** on their profile — new quotations
and invoices for them default to no GST charged. This is different from
Overseas: the client is still in India, NEDS is just not charging GST on
their bills. Can still be overridden per document if needed.

**Create Meeting and Meet notes (optional):** once an admin has connected
NEDS's Google account (**Profile → Google Account**, admin-only — nothing
you need to do yourself), open a client or lead's **Calls** tab and click
**Create Meeting** to schedule a real Google Meet call — pick a time, and
it invites the client's email automatically and shows you the link to share
directly too (handy for WhatsApp). No Google account of your own needed.
**Inviting teammates:** the scheduler also lets you pick any active
colleague to invite internally — they get a bell notification, a card on
their own Dashboard with **Accept**/**Decline** buttons, and a **Join
Google Meet** link, all without needing a real Google Calendar invite.
Each meeting's page shows every invited teammate's current status next to
the client's name. **Import Meet Notes** is for a call that already
happened outside that flow — it pulls in the recording link, transcript
link, and full transcript text Google Meet already generated, the same way
a logged call shows up (needs Recording/Transcripts turned on for that
call, and a few minutes after it ends to finish processing). Both work the
same way for Leads too. If AI is also enabled, an imported meeting with a
transcript gets a **Summarize with AI** button (or the summary appears
automatically a little after import) — Claude turns the raw transcript
into short "Key points / Decisions / Action items" notes, visible to
anyone who opens that client's page. If it fails, a **Retry** link appears
in its place.

**Meetings held on Zoom, Microsoft Teams, or anywhere else:** click **+ Log
External Meeting** on the same Calls tab — this works even if no Google
account is connected at all, since it's independent of the integration
above. Pick the platform, when it happened, an optional duration, and paste
in your notes or a transcript. If AI is enabled, whatever you paste gets
summarized the same way an imported Google Meet transcript does.

**Monthly wins note (AI, optional):** on the 1st of each month, if AI is enabled
and one of your clients had tasks completed, tickets resolved, a payment, or
(for clients on Drishti) posts published/audits completed in the month before,
Claude drafts a short "here's what we delivered" note and adds it to that
client's **Notes** tab (marked "AI-drafted monthly update"). It's
staff-only — the client never sees it automatically. Review it, personalize it,
and send it yourself via email or WhatsApp if it reads right.

**Daily project update drafts (AI, optional):** if AI is enabled, every evening
at 6:30 PM Claude drafts a short client-facing update for each of your active
projects that had a task completed that day (skipped on Sundays, and skipped
entirely if nothing was completed). Unlike the monthly wins note, this one has
a built-in send step: open the project page and you'll see a **Pending Client
Update** panel above Notes & Client Updates. Edit the wording if you like, then
click **Approve & Send** — this posts it to the client's portal under "Updates
from Our Team" *and* emails it to the client's billing contact, in one step.
Click **Discard** instead if a day's draft isn't worth sharing. Nothing reaches
the client until you approve it.

## 4a. Contract & Renewal Dashboard
Sidebar → **Contract & Renewals** (shared with Manager and Accounts) —
active recurring contracts ending in the next **30, 60, or 90 days**, each
with a **renewal status** you set: Not Started → Discussion → Proposal
Sent → Negotiation → Renewed/Lost. Use it to see which of your clients'
renewals actually have a conversation happening and which are about to
lapse with nobody talking to the client yet.

## 5. Incentives
Sidebar → **Incentives** shows your monthly sales incentive, calculated live
from the same "won this month" value shown on the Sales Dashboard (so the two
pages never disagree):
- **Individual incentive** — a tiered rate on your before-tax sales this
  month: 6% up to ₹50,000, 10% up to ₹1,00,000, 12.5% up to ₹1,50,000, 15% up
  to ₹2,50,000, 20% above that. Rates are marginal, like income tax — each
  slab's rate only applies to the portion of sales *within* that band, so
  there's never a reason to hold a deal back to avoid crossing a boundary.
- **Team bonus** — a fixed pool (set by Admin/Manager) split evenly across
  every active Sales rep, paid only in months the company-wide monthly target
  (set on the Sales Dashboard) is met.
- **Slab progress bar** shows where this month's sales sit across the five
  bands, plus how much more you need to reach the next rate.
- **Finalized history** — once a month closes, the 1st-of-the-month job locks
  that month's numbers into a permanent record (so editing an old deal later
  never changes a past month's incentive). Anything showing for the current,
  still-open month is a live estimate, not yet finalized.

## 6. Content pieces (when you're a project's Project Manager)
If you are set as the **Project Manager** of a project, you can track content
pieces for that project — useful when NEDS is managing social media or other
content for a client alongside a partner agency.

**Adding a content piece:** open the project → **Content Pieces** card →
**Add piece**. Choose the workflow type:

- **Agency-led** — the partner agency creates the content and delivers it to
  NEDS for publishing. Starts in *Pending from agency*.
- **NEDS-led** — NEDS writes the copy/brief first, sends it to the partner for
  visuals, then publishes. Starts in *Copy drafting*. **If the copy was written
  in SMDost and marked "Send to agency" there, the piece is created
  automatically** in *Sent to partner* status — no need to add it manually.

Fill in the platform (Instagram, Facebook, etc.), title, publish date, and any
notes. Assign a partner if one is involved.

**Advancing status:** open the content piece → click the **Move to…** button
to move it to the next step. When a piece is marked *Published*, the timestamp
is recorded automatically.

**Google Drive:** you can store a Drive link on each piece (for the specific
file) and on the project itself (for the shared folder). These appear as
clickable links so you can jump straight to the asset.

**Upload links** (generating one requires manager access — ask your manager).
Once a link is generated you can copy it and send it to the partner; they
upload files without needing a CRM login.

## Bell notifications
The 🔔 bell icon at the top of the screen shows real-time alerts for your key
events. You'll be notified when:

| Event | Who gets it |
|---|---|
| 🟢 **New lead created** | The assigned sales person — or all sales staff if the lead has no owner yet (rare now that leads auto-assign) |
| 🔥 **Hot lead (AI score ≥ 70)** | The lead's owner, as soon as the score comes back — no need to wait for the morning digest |
| ✨ **Nurture follow-up drafted** | The lead's owner, when a New lead has gone 1/3/7 days with no note or call from you |
| 📄 **New quotation created** | The deal's assigned sales person |
| 🏆 **Deal marked Won** | The deal's assigned sales person + all managers and admins |
| 🧾 **New invoice created** | The client's assigned sales person (recurring auto-invoices are excluded to avoid noise) |
| 📈 **Monthly wins note drafted** | The client's assigned sales person, on the 1st of each month for clients with something to report the month before |
| 📝 **Project daily update ready to review** | The project owner, at 6:30 PM on days a task was completed on one of their projects |

Click any notification to jump straight to the record. Click **Dismiss** to clear it.

## Email alerts
The CRM sends you two types of automated emails to help you stay on top of things:

**Morning digest (9 AM daily)** — a summary of your day: overdue tasks, tasks due
today, call follow-ups due, lead follow-ups, deal follow-ups, and open tickets.
No email means your slate is clean. If AI is enabled, it opens with a short
AI-written line on what to prioritise — the same line also shows as a banner
on your dashboard for the rest of the day.

**Stagnation alert (10 AM daily)** — if any of your leads haven't had any
activity (note, call, or edit) for **7 days**, or any of your deals for
**10 days**, you'll get a reminder. Even adding a brief note resets the clock.

## Tips
- Use the **search bar** to jump straight to any lead, client or deal.
- Keep **notes** and **call outcomes** up to date — they drive your follow-up
  list, your performance report, and the stagnation clock.
