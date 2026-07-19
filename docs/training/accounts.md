# Accounts — Recording Script

**Audience:** accounts team (watch `getting-started.md` first).
**Target length:** ~13 minutes

**Before you record:** have one invoice with a "Pending Invoice #" badge,
one already-numbered invoice, one recurring invoice template, one invoice
with a broken payment promise, and one milestone marked Done-but-not-invoiced
ready to click through. Log in as an accounts user.

---

## Scene 1 — Intro + dashboard (0:00–0:30)
**ON SCREEN:** Accounts dashboard.
**SAY:** "This one's for accounts. You own invoices, payments, recurring
billing, and the revenue and cash reports. Your dashboard shows outstanding
receivables, what's been collected this month, and how many invoices are
overdue."

## Scene 2 — GST-compliant invoices (0:30–1:30)
**ON SCREEN:** Open an invoice, point at the GSTIN, tax breakup, and
amount-in-words sections.
**SAY:** "Every invoice here is GST-compliant. Maharashtra clients get
CGST plus SGST; other Indian states get IGST at the full rate; overseas
clients are zero-rated automatically — no GST, and the PDF says 'Export of
Services' instead — that's driven entirely by the Country field on the
client, nothing to set manually. There's also a Non-GST client checkbox on
a client's profile, for a domestic client who's arranged to be billed
without GST — different from Country, they're still in India, we're just
not charging them GST. Each invoice number looks like NEDS/2026-27/0042,
shows both parties' GSTINs, HSN codes, the tax breakup, and the amount in
words."

## Scene 3 — Assigning the invoice number (1:30–2:30)
**ON SCREEN:** Open the invoice with the pending badge, click Assign
Invoice Number.
**SAY:** "When sales converts a quotation to an invoice, it lands here with
a yellow 'Pending Invoice #' badge — no GST number yet. Open it and click
Assign Invoice Number to generate the next sequential number. You can't
send or download the PDF until you do this — it's the gate that keeps
numbering sequential and correct. And if an invoice was created by
mistake, Admin, Manager, or Accounts can delete it — even one with a
payment recorded, which removes the payment too, so use that deliberately.
For a GST mistake on an invoice that's already been paid, a credit note is
usually the right tool instead of deleting."

## Scene 4 — Recording a payment, with TDS (2:30–4:15)
**ON SCREEN:** Open an invoice → Record a payment → fill amount/date/mode
→ enter a TDS amount → point at the TDS tile → tick the receipt checkbox →
Record.
**SAY:** "To record a payment, open the invoice, fill in amount, date, and
mode — UPI, NEFT, cheque, cash, or gateway. Partial payments are fine — the
invoice moves to Partially Paid, then Paid once it's fully settled,
automatically. If the client deducted TDS before paying, enter that in the
TDS Amount field alongside the payment — the invoice counts as fully
settled once amount paid plus TDS deducted reaches the total, even though
the actual cash you received is less. You'll see a TDS tile next to
Total/Paid/Balance, and the PDF adds a 'TDS deducted' line automatically
once any TDS has been recorded. If the client has an email on file, you'll
see a 'Send payment receipt to client' checkbox — tick it before you click
Record and they get an emailed confirmation with the amount, mode, and
remaining balance. Once you save, a bell notification goes out to the rest
of accounts and the client's sales rep — purely informational, nothing for
you to do there."

## Scene 5 — Payment promise tracking (4:15–5:15)
**ON SCREEN:** Open an unpaid overdue invoice → set "Client promised to pay
by" → point at a different invoice already showing the red "Payment promise
broken" badge.
**SAY:** "When a client says 'I'll pay in a day or two' past the due date,
don't just remember it — open the invoice and set 'Client promised to pay
by' to that date. If it passes and the invoice is still unpaid, it gets
flagged Payment promise broken, right here and on the Collections report.
You don't have to go check for it either — the morning after a promised
date breaks, you and admin/managers get a bell notification automatically.
It fires once per promise, and again if a new promise is made and also
breaks. Use the notes box on the same page to log what was actually said
each time you follow up."

## Scene 6 — Milestone billing (5:15–6:15)
**ON SCREEN:** Open a quotation's Milestone Manager, point at a milestone
marked Done with a green "Ready to invoice" badge, and — if any task is
linked to it — the "Mark this milestone Done?" suggestion banner.
**SAY:** "For milestone/project work, each milestone on the quotation has
its own status — Pending, In Progress, Done — separate from whether it's
been invoiced. Whoever's running the project marks it Done once that phase
is actually finished. Once it's Done and not yet invoiced, you'll see a
green 'Ready to invoice' badge here and on the Collections report — that's
your cue to raise the next invoice. If the project's tasks are tagged to
this milestone, you might also see a one-click 'mark this Done?' banner
once every linked task is finished — still just a suggestion, someone has
to click it, nothing flips automatically."

## Scene 7 — Recurring invoices (6:15–8:00)
**ON SCREEN:** Invoices → Recurring Invoices → open a template → show its
generated-invoice history → Send Email on one row → point at an "Ended"
template vs. an "On Hold" one.
**SAY:** "For monthly retainers — SEO, GMB, social, ads, AMC — set up a
recurring template here. Every morning the scheduler checks for due
templates and auto-generates and emails the invoice — you don't have to do
anything. Click into a template to see its full history of generated
invoices; from there you can resend an invoice by email, or click View/Pay
to record a payment on any of them, same as a normal invoice. Two statuses
worth knowing apart: Ended means the template had an end date that's
passed and it won't bill again, nothing to do — On Hold means someone
paused it, or it has no end date and just isn't active, worth checking if
it should be resumed. Clients also get automatic reminder emails 7, 5, 3,
and 1 day before each billing date, and 30 days before an active
contract's end date you and admin/managers get a renewal reminder — all
hands-off. And every morning at 8 AM, if any recurring invoice is due in
exactly 7 days and unpaid, you get a bell notification too."

## Scene 8 — Deleting a recurring template (8:00–8:30)
**ON SCREEN:** Point at the Delete button on a template.
**SAY:** "If a retainer ends, open the template and delete it — that
removes the template and its future schedule, but every invoice it already
generated stays untouched for your records. Pause is different from
Delete — Pause just stops future billing but keeps the template around as
On Hold, so if you're cleaning up a duplicate or test template, make sure
you actually click Delete."

## Scene 9 — Collections worklist (8:30–9:45)
**ON SCREEN:** Sidebar → Collections → point at the client picker (All /
Direct / one partner) and a row showing recurring-not-paid, partial, and
promise-broken status.
**SAY:** "Collections, in the sidebar, is your client-by-client follow-up
worklist — this is the page to work down each morning if you're chasing
payments. For every client with something outstanding, it shows what's
recurring and unpaid, what's partially paid, any broken payment promise,
how many days overdue, and for milestone-billed projects, the completion
percent and whether the next milestone is ready to invoice. Use the picker
at the top to see all clients, only direct clients, or just one partner's."

## Scene 10 — Cash Forecast (9:45–10:45)
**ON SCREEN:** Dashboard → Reports → Cash Forecast.
**SAY:** "Cash Forecast is your near-term cash view, bucketed by the next
three months — recurring revenue expected from active templates, plus
receivables already invoiced and due, with anything already overdue
collapsed into the current month since it's owed right now regardless of
its original date. The open pipeline's weighted forecast is shown
separately and clearly marked indicative — it's not blended in as if it
were committed cash, since deals don't have an expected-close date to
bucket by."

## Scene 11 — Reports and wrap-up (10:45–12:30)
**ON SCREEN:** Sidebar Account → outstanding receivables, then Dashboard →
Reports → Revenue Report, then Business Overview.
**SAY:** "For the rest of reporting: the sidebar's Account report shows
outstanding receivables — who owes what and how overdue. From the
dashboard's Reports panel, the Revenue Report breaks income down by month,
by service, and by client, split recurring versus one-time — export
either to CSV. Business Overview is the executive snapshot — as Accounts
you see the full detail, same as Admin: AR aging broken into
current/0-30/31-60/61-90/90-plus day buckets with the itemized overdue
invoice list, the full MRR breakdown by service and which contracts expire
in the next 30 days, and the named top client breakdown behind the
concentration percentages. Two habits that matter most: assign the invoice
number before trying to send anything — the button's disabled until you
do — and record payments promptly so the dashboard numbers stay accurate.
Also worth knowing, none of the daily digest or reminder emails go out on
Sundays."
