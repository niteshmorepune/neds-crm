# Accounts guide

You handle **invoices**, **payments**, **recurring billing**, and the
**receivables / revenue** reports.

## Your dashboard
- **Outstanding receivables** — total still owed to NEDS. Always matches the
  **Receivables report** total exactly (same underlying figure) — an invoice
  whose client record has since been removed still counts here and shows as
  "Client removed" on the Receivables report, rather than silently
  disappearing from either number.
- **Collected this month** — payments received. Click **View payments
  collected** below the number for the individual payments behind it (date,
  client, invoice, mode, who recorded it).
- **Overdue invoices** — count past their due date. Click **View overdue
  invoices** below the number to see exactly which ones.
- **Overdue tasks alert** — if you have any tasks past their due date, a red
  banner appears at the top of the dashboard with a direct link to your task list.
- **Your Productivity This Month** — your rank among other Accounts staff
  this month, an overall score, and your biggest opportunity area. Private
  to you — nobody else sees your rank, and you don't see anyone else's. If
  AI is enabled, click **Get tips to improve** for a specific suggestion
  based on your own numbers.

## 1. Invoices
**Invoices** lists every invoice. Invoices are **GST-compliant**:
- **Maharashtra clients** → tax splits into **CGST + SGST**.
- **Other states (India)** → **IGST** at the full rate.
- **Overseas clients** → **zero-rated** (export of services, no GST). The PDF
  is labelled "INVOICE" with an "Export of Services / Zero-Rated Supply" note
  instead of the usual tax rows. This is set automatically when the client's
  **Country** is anything other than "India" — no manual adjustment needed.
- Each invoice has a number like **NEDS/2026-27/0042** (the financial year runs
  April–March), shows both parties' GSTINs, HSN/SAC per line, the tax breakup,
  and the **amount in words**.

**Creating an invoice:** usually a salesperson converts an accepted **quotation**
into an invoice; it then appears here ready for your review. When first created
the invoice shows a yellow **"Pending Invoice #"** badge — it has no GST number
yet. Open it and click **Assign Invoice Number** to generate the next sequential
NEDS invoice number (e.g. `NEDS/2026-27/0042`). You must assign a number before
you can send or download the invoice PDF.

**Milestone/project billing:** a single deal can be billed in parts (e.g. advance
on signing, balance on delivery) — each milestone becomes its own invoice. These
also start with a pending number; assign it when you're ready to issue the invoice.

**"Log Invoice" (top of the Invoices list) is for recording an invoice already
issued outside the CRM** — historically in Hitech, the older billing tool —
so it shows up here for record-keeping, alongside real client history. **Always
type that invoice's actual Hitech number in the "Hitech Invoice Number" field
(e.g. `HT-2026-0042`), never a `NEDS/...`-style number.** NEDS-format numbers
are reserved for the CRM's own auto-generated sequence — typing one in here
manually, especially alongside a back-dated invoice date, can collide with a
number the system later tries to hand out automatically. If you're recording
several old invoices at once, the **CSV import** on the same screen is safer
than typing them in one at a time.

**Milestone work status:** each milestone on the quotation now has a status —
**Pending / In Progress / Done** — separate from whether it's been invoiced.
Whoever is running the project marks a milestone **Done** once that phase of
work is actually finished (e.g. "UAT complete"). Once Done and not yet
invoiced, it shows a green **Ready to invoice** badge on the quotation's
Milestone Manager and on the **Collections** report (below) — that's your
signal to raise the next invoice. This is still set manually by the team —
nothing flips it automatically.

**Milestone Done suggestion:** if the project's tasks (Emptask) are tagged to
a milestone via the task's **Milestone** field, the Milestone Manager shows a
one-click "All N linked tasks done — mark this milestone Done?" nudge once
every linked task is completed. It's only a suggestion — someone still has to
click it, so a milestone never flips to Done on its own just because tasks
finished.

**Non-GST clients:** some clients need a plain bill with no GST charged. Open
the client and tick **Non-GST client** — new quotations and invoices for them
default to no GST (PDF shows "INVOICE" instead of "TAX INVOICE", no CGST/SGST/
IGST rows). You can still override it per document with the same checkbox on
the Quotation/Invoice builder if a one-off exception is needed. To fix an
invoice that was wrongly issued as GST, open it in **Edit** and untick the GST
box — this only works before any payment has been recorded; once a payment
exists the invoice is locked and needs a credit note instead.

**Deleting an invoice:** if an invoice was created by mistake or is otherwise
wrong, open it and click **Delete** (Admin, Manager, and Accounts can all do
this). This works even if a payment has already been recorded against it —
deleting removes the invoice and any of its payments together, so use it
deliberately, not as a routine correction (for a GST mistake on an
already-paid invoice, a credit note is usually the right tool instead — see
above). Deleted invoices and payments are kept internally in case they ever
need to be recovered, but they no longer show up anywhere in the CRM.

## 2. Recording payments
Open an invoice → **record a payment** (amount, date, mode — UPI / NEFT / cheque
/ cash / gateway). **Partial payments are allowed**: the invoice moves to
*Partially paid*, then *Paid* once fully settled. Status updates automatically.

**TDS (Tax Deducted at Source):** if the client deducted TDS before paying,
enter it in the optional **TDS Amount** field alongside the payment amount.
The invoice's TDS total and balance update automatically — an invoice counts
as fully settled once **amount paid + TDS deducted** reaches the total, even
though the actual cash received is less. The invoice page shows a TDS tile
next to Total/Paid/Balance, and the PDF adds a "TDS deducted" / "Net payable"
line once any TDS has been recorded on that invoice.

**Sending a payment receipt:** when any of the client's contacts has an email
address on file, a **Send payment receipt to client** checkbox appears at the
bottom of the Record Payment form. Tick it before clicking Record — the client
receives an email confirming the amount received, payment mode, reference number,
and the remaining balance (or a "fully settled" message if the invoice is now
paid in full).

**Bell notification — payment recorded:** when a payment is saved, a 💰 bell
notification is sent to all accounts staff (except the person who recorded it)
and to the client's assigned sales person. No action needed — it's purely
informational so everyone stays aware of cash coming in.

**Correcting a mistaken payment date, mode, or reference:** click **Edit** next
to any payment already listed on the invoice page to fix a typo without
deleting and re-recording it — an inline form lets you change the **date**,
**mode**, and **reference number**. The **amount and TDS are not editable
here** — if either of those was entered wrong, you still need to delete the
payment and record it again, since those two drive the invoice's balance and
status and may have already triggered a payment-received email to the client.
Every correction is logged to the activity trail for an audit record.

**Payment follow-up tracking:** when a client says "I'll pay in a day or two"
past the due date, open the invoice and set **Client promised to pay by** to
that date, so it's not just in your head. If that date passes and the invoice
is still unpaid, it's flagged **Payment promise broken** (red badge on the
invoice, and on the **Collections** report below) — a clear signal to call
again. Use the notes box on the same page to log what was actually said each
time you follow up.

**Bell notification — payment promise broken:** you no longer have to
remember to check. The morning after a promised date passes with the invoice
still unpaid, a 🚩 bell notification goes out to accounts staff, admin, and
managers. It fires once per promise — if the client gives you a new date and
that one breaks too, you'll get a fresh notification for it.

## 3. Recurring invoices
For monthly retainers (SEO, GMB, social, ads, AMC), set up a **recurring invoice
template** (under Invoices → Recurring Invoices). The system **auto-generates
the invoice each cycle** (every morning the scheduler checks for due templates)
and emails it to the client automatically. You can pause/resume a template
anytime.

**"Ended" vs. "On Hold" / "Paused" (Invoices → Recurring Invoices list):** this
describes the *template's own schedule*, not whether its invoice got paid.
**Ended** means the template had an end date, that date has passed, and it
won't bill again — nothing to do, it ran its course. **On Hold** / **Paused**
means someone clicked Pause, or the template simply has no end date set yet
still isn't active — check whether it should be resumed. Ended templates are
hidden from this list by default (they'd otherwise pile up forever) — click
**Show ended templates** to see them again. If you set up one template per
one-month period, expect it to flip to Ended right after its single invoice
generates; if you want an ongoing retainer to keep billing every month
automatically, leave **End date** blank instead of re-creating a new template
each month.

**One recurring template per billing month is the normal way to do
month-by-month invoicing and payment tracking** — for either a current
client's ongoing monthly retainer or an older client's still-pending
historical months. A template auto-pausing right after generating its one
invoice is expected, not an error. It's fine and safe to click **Activate**
on it again afterward — e.g. to regenerate a corrected invoice for that
same month (delete the wrong one first, then Activate + **Generate & Send
Now**), or simply to keep it available while that month's payment is still
outstanding. Reactivating never risks a surprise duplicate: the unattended
daily scheduler only ever auto-generates from a template whose next run is
still within its own end date, so a reactivated one-cycle template just
sits there until *you* generate from it again on purpose.

**Status on the client's Services tab is different — it tracks the period,
not the schedule:** the same recurring template shows one of **Upcoming**
(hasn't started yet), **Active** (today falls within its period and it's
still on), **On Hold** (today falls within its period but it's paused),
**Payment Received** / **Payment Pending** (the period is over — based on
whether its generated invoice is actually paid), or **Not Billed** (the
period is over and no invoice was ever generated for it — e.g. a template
that was paused before its first billing cycle, kept around for historical
record). The Payment Received/Pending detail only shows to roles with
invoice access (e.g. not Support) — everyone else sees **Ended** for a
finished period regardless of its payment status (this "Ended" wording is
specific to that restricted view — Admin/Manager and anyone else with
invoice access never see it; they see Not Billed instead when nothing was
ever generated). One template deliberately never shows here at all: if its
only invoice was generated and then deleted, and it was never reactivated,
it's treated as abandoned and hidden from the client's view entirely rather
than showing a misleading On Hold/Not Billed row for something that was
retracted.

**Non-GST clients:** when you pick a client marked **Non-GST client** on their
profile, the template's **Non-GST client** checkbox is ticked automatically —
every invoice generated from that template (auto or via Generate & Send Now)
skips GST. You can still tick or untick it yourself for a one-off exception;
once saved, the template keeps whatever you set even if the client's own
Non-GST setting changes later, so update the template directly if that ever
needs to change. When Non-GST is checked, each line item's GST% field is
grayed out with a note — it's ignored, the invoice will show ₹0 tax
regardless of what's in that field. The **Est. / cycle** figure on the
client's Services tab reflects this too — it only shows the "+GST" hint when
the template will actually charge GST.

**Deleting a recurring template:** if a retainer has ended, open the template and
click **Delete** (or use the Delete button on the Recurring Invoices list). This
removes the template and its schedule; **previously generated invoices are kept**.
**Pause is not the same as Delete** — Pause just stops future billing but keeps
the template (it'll show as On Hold); if you're cleaning up a duplicate or
test template you created by mistake, make sure you click **Delete**, not
Pause, or it'll still be there.

**Viewing generated invoices:** click **Invoices** on any recurring template row
to open its history — every invoice that has been auto-generated for that
template, with status, balance, and action buttons.

**Resending an invoice by email:** on the recurring template's invoice history
page, click **Send Email** on the row you want to resend. The same
`InvoiceIssued` email goes to the client's billing contact.

**Recording a payment:** click **View / Pay** on any generated invoice row to
open the full invoice page, then use the **Record payment** form at the bottom.
Partial payments are supported.

**Advance reminders to the client:** the system automatically emails the client
**7, 5, 3, and 1 day before** each upcoming billing date so they're never
surprised. No action needed from you — the scheduler sends these at 09:00 IST daily.

**Bell notification — invoice due in 7 days:** every morning at **8 AM**, if any
recurring-linked invoice has a due date exactly 7 days away and hasn't been paid
yet, a ⚠️ bell notification is sent to all accounts staff, admin, and managers.
This gives you a full week to follow up before the invoice becomes overdue.

**Bell notification — contract renewal due soon:** every morning at **8:30 AM**,
if an active recurring template's **end date** falls within the next 30 days,
you (and admin/manager, plus that client's sales rep) get a 📅 bell
notification — a nudge to renew the contract or follow up before it lapses.
Fires once per end date; if the contract is renewed to a later date, you'll be
notified again once that new date comes within 30 days.

## 4. Reports
- **Account** (in the sidebar) → the **outstanding receivables** report: who owes
  what, and how overdue.
- **Collections** (in the sidebar) → the client-by-client follow-up worklist.
- **Cash Forecast** (linked from Business Overview) → recurring revenue
  expected + receivables due, bucketed by the next 3 months, plus the
  open pipeline's weighted forecast shown separately (it's indicative, not
  committed cash — deals don't have an expected-close date to bucket by month).
  For each client with something outstanding: how much is **recurring not
  paid** (a retainer client hasn't paid at all), **other unpaid** (a one-time/
  milestone invoice with zero payment), and **partial — pending** (some paid,
  some still owed), plus the oldest overdue days (with an approximate month
  count alongside it), any payment-promise date
  (red if broken), and — for milestone-billed projects — the completion %
  and whether the next milestone is **Ready to invoice**. Use the picker at
  the top to view **All clients**, **Direct clients** (no referring partner),
  or one specific partner's clients only. This is the page to work down each
  morning if you're chasing payments.
- From the **Dashboard → Reports** panel → **Revenue Report**: income by month,
  by service, and by client, split into **recurring vs one-time**. Export to CSV.
- From the same panel → **Business Overview**: the executive snapshot. As
  Accounts you get the full financial detail (same as Admin) — AR aging broken
  into current/0-30/31-60/61-90/90+ day buckets with the itemized overdue
  invoice list, the full MRR breakdown by service plus which recurring
  contracts expire in the next 30 days, and the named top-5/top-10 client
  breakdown behind the concentration percentages. Also shows partner referral
  performance (as plain text, not a link — a partner's own page with their
  billing/collections detail is Admin/Manager only) and the company-wide
  pipeline/win-rate funnel. Export to CSV.

## 5. Invoice emails
Invoice emails sent to clients now have a **professional branded layout**: NEDS
header, invoice summary card (number, amount, dates), amount in words, and —
when bank details are configured — a **Payment Details** panel with account number,
IFSC code, and UPI ID. The same details appear on the PDF. Bank details are set
in the server `.env` (`COMPANY_BANK_NAME`, `COMPANY_ACCOUNT_NUMBER`, etc.) — ask
your developer to update them.

## Tips
- **Assign the invoice number before sending** — the Send button is disabled until
  a number has been assigned.
- Record payments promptly so receivables and the dashboard stay accurate.
- Overdue invoices are flagged automatically each morning, and payment reminders
  go out to clients.
- Daily digest and reminder emails are **not sent on Sundays**.
- If you delete a recurring invoice template, the previously generated invoices
  are kept for financial records — only the template and its schedule are removed.
