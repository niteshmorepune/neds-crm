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

> **Note:** leads that existed before AI was enabled show no badge until you
> open them, make any edit (even adding a note), and save.

**Working a lead:**
- Open the lead to add **notes**, see the timeline, and log activity.
- **Log a call** (top bar or the lead page) after you phone them — record the
  outcome (connected, no answer, follow-up needed).
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
- Won and Lost are final — once set, a deal stays there.
- A **won** deal can become a **Project** for the delivery team.
- **Referred by** — if a deal came to NEDS through a partner agency rather than
  directly, open the deal and set the **Referred by** dropdown to that agency.
  Leave it as "Direct (no agency)" for clients who came to NEDS on their own.
  This lets management see which deals were agency-sourced vs direct.

## 3. Quotations
1. **Quotations → Create** — or open a **Deal** and click **+ New Quotation**
   directly on the deal page (the client and deal are pre-filled for you) — or
   open a **Lead** and click **Send Quotation** (the CRM converts the lead and
   pre-fills the builder for you automatically).
2. Add **line items** (description, HSN/SAC, quantity, rate). GST is calculated
   per line (CGST+SGST for Maharashtra clients, IGST otherwise).
3. For milestone/project work, add **milestones** (e.g. advance on signing,
   balance on delivery).
4. Save, then **download the PDF** to send to the client.
5. When the client agrees, mark the quotation **Accepted**, then **Convert to
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

**Monthly wins note (AI, optional):** on the 1st of each month, if AI is enabled
and one of your clients had tasks completed, tickets resolved, a payment, or
(for clients on Drishti) posts published/audits completed in the month before,
Claude drafts a short "here's what we delivered" note and adds it to that
client's **Notes** tab (marked "AI-drafted monthly update"). It's
staff-only — the client never sees it automatically. Review it, personalize it,
and send it yourself via email or WhatsApp if it reads right.

## 5. Content pieces (when you own a project)
If you are set as the **owner** of a project, you can track content pieces for
that project — useful when NEDS is managing social media or other content for
a client alongside a partner agency.

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
| 🟢 **New lead created** | The assigned sales person — or all sales staff if the lead has no owner yet |
| 📄 **New quotation created** | The deal's assigned sales person |
| 🏆 **Deal marked Won** | The deal's assigned sales person + all managers and admins |
| 🧾 **New invoice created** | The client's assigned sales person (recurring auto-invoices are excluded to avoid noise) |
| 📈 **Monthly wins note drafted** | The client's assigned sales person, on the 1st of each month for clients with something to report the month before |

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
