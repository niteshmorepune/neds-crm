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

## 1. Leads (Lead Generation)
1. **Lead Generation → Add Lead**.
2. Fill in name, company, phone/email, **source**, the **service** they're
   interested in, and an **estimated value**. Assign an **owner** (usually you).
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
as you're assigned.

**Campaign source:** if a website lead came in through a tracked ad or link,
the lead page shows a **Campaign** line (e.g. "google / cpc /
seo-pune-2026") so you know which channel it came from before you call.

**WhatsApp leads:** a WhatsApp message from a number that isn't an existing
client's now creates a lead automatically (source = WhatsApp) instead of
being dropped. If the same unknown number messages again before you've
converted them, the CRM adds it as a note on the same lead rather than
creating a duplicate.

**Meta Ads leads:** a submission on a Facebook or Instagram lead ad creates a
lead automatically (source = Meta Ads). Any question on the ad form beyond
name/email/phone/company (e.g. a custom budget question) appears as a note
on the lead.

**Automatic nurture follow-ups:** if a New lead sits with no note or logged
call from you for **1, 3, or 7 days**, the CRM drafts a follow-up message for
you automatically — it appears as an "✨ AI-drafted follow-up (touch N/3)"
note on the lead, and you get a bell notification. Copy it into WhatsApp or
email (editing as needed) and send it yourself — nothing is ever sent
automatically. The moment you add your own note or log a call on a lead, the
sequence stops (the CRM assumes you've taken it from here).

**Working a lead:**
- Open the lead to add **notes**, see the timeline, and log activity.
- **Log a call** (top bar or the lead page) after you phone them — record the
  outcome (connected, no answer, follow-up needed).
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
  just those.
- Set a **next follow-up date** on the lead itself so it shows up in your
  "follow-ups due" dashboard widget.
- **Draft follow-up (✨)** — click this button to have AI write a suggested
  follow-up message based on the lead's details and history. Read it, edit it to
  match your voice, then send it yourself (WhatsApp, email, or call). The AI
  never sends anything automatically.

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

## 2. Pipeline (Sales Department)
The **Sales Department** board shows your deals in columns by stage:
**New → Contacted → Proposal → Negotiation → Won / Lost**.

- **Drag a deal** to the next column as it progresses (or open it and change the
  stage).
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
  It drives every figure in the KPI strip below, so a missing or 0 value
  understates your own numbers.
- **Deals like this one** — on a deal's page, a panel shows up to 3 other
  closed deals (Won or Lost) for the same service, ranked by how close their
  value is to this one — useful context on how deals with this profile tend
  to actually go. It needs a service set on the deal to show anything, and
  won't appear until this service has at least one other closed deal to
  compare against.

**KPI strip** — above the board, seven figures scoped to your own deals
(Admin/Manager see the whole company's pipeline instead):
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

**Stale-deal badge** — each card shows how many days it's been sitting in its
current stage, turning red past 10 days. A red badge is a nudge to follow up
or move the deal, not an automatic penalty.

## 2a. Sales Dashboard
A dedicated page (Sidebar → **Sales Dashboard**) for a fuller view than the
board's KPI strip — same numbers, scoped the same way (your own deals; Admin/
Manager see everyone):
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

## 2b. Incentives
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

## 3. Quotations
1. **Quotations → Create** — or open a **Deal** and click **+ New Quotation**
   directly on the deal page (the client and deal are pre-filled for you) — or
   open a **Lead** and click **Send Quotation** (the CRM converts the lead and
   pre-fills the builder for you automatically).
2. Add **line items** (description, HSN/SAC, quantity, rate). GST is calculated
   per line (CGST+SGST for Maharashtra clients, IGST otherwise).
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
   invoice.
5. Save, then **download the PDF** to send to the client.
6. When the client agrees, mark the quotation **Accepted**, then **Convert to
   invoice** — accounts takes it from there.

All quotations linked to a deal are listed on the deal's page so you can track
which version the client accepted.

## 4. Clients
**Clients** lists the companies assigned to you, plus any unassigned ones. Only
your clients and unassigned clients appear in your list — clients owned by other
sales reps are not shown. Open a client to see its contacts, notes, calls, deals,
invoices and tickets in one place.

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

**Meet notes (optional):** if enabled, connect your own Google account once
from **Profile → Google Account**, then open a client's **Calls** tab and
click **+ Import Meet Notes** to pull in your recent Google Meet calls with
that client — the recording link, transcript link, and full transcript text
Google Meet already generates are saved right there, the same way a logged
call shows up. Read-only: nothing in your Calendar or Drive is ever changed.
Only meetings you personally organized and recorded will have anything to
import (Google needs Recording/Transcripts turned on and a few minutes after
the call ends to finish processing). This currently works the same way for
Leads too. If AI is also enabled, an imported meeting with a transcript gets
a **Summarize with AI** button (or the summary appears automatically a
little after import) — Claude turns the raw transcript into short "Key
points / Decisions / Action items" notes, visible to anyone who opens that
client's page. If it fails, a **Retry** link appears in its place.

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

## 5. Content pieces (when you're a project's Project Manager)
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

## 6. Quotations — sending to clients
Once a quotation is ready, open it and click **Send to Client**. This emails the
quotation details to the client's billing address and marks it as Sent. The client
can then accept or request changes.

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
