# Accounts guide

You handle **invoices**, **payments**, **recurring billing**, and the
**receivables / revenue** reports.

## Your dashboard
- **Outstanding receivables** — total still owed to NEDS.
- **Collected this month** — payments received.
- **Overdue invoices** — count past their due date.

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

## 2. Recording payments
Open an invoice → **record a payment** (amount, date, mode — UPI / NEFT / cheque
/ cash / gateway). **Partial payments are allowed**: the invoice moves to
*Partially paid*, then *Paid* once fully settled. Status updates automatically.

**Sending a payment receipt:** when any of the client's contacts has an email
address on file, a **Send payment receipt to client** checkbox appears at the
bottom of the Record Payment form. Tick it before clicking Record — the client
receives an email confirming the amount received, payment mode, reference number,
and the remaining balance (or a "fully settled" message if the invoice is now
paid in full).

## 3. Recurring invoices
For monthly retainers (SEO, GMB, social, ads, AMC), set up a **recurring invoice
template** (under Invoices → Recurring Invoices). The system **auto-generates
the invoice each cycle** (every morning the scheduler checks for due templates)
and emails it to the client automatically. You can pause/resume a template
anytime.

**Deleting a recurring template:** if a retainer has ended, open the template and
click **Delete** (or use the Delete button on the Recurring Invoices list). This
removes the template and its schedule; **previously generated invoices are kept**.

**Viewing generated invoices:** click **Invoices** on any recurring template row
to open its history — every invoice that has been auto-generated for that
template, with status, balance, and action buttons.

**Resending an invoice by email:** on the recurring template's invoice history
page, click **Send Email** on the row you want to resend. The same
`InvoiceIssued` email goes to the client's billing contact.

**Recording a payment:** click **View / Pay** on any generated invoice row to
open the full invoice page, then use the **Record payment** form at the bottom.
Partial payments are supported.

**Advance reminders:** the system automatically emails the client **7, 5, 3, and
1 day before** each upcoming billing date so they're never surprised. No action
needed from you — the scheduler sends these at 09:00 IST daily.

## 4. Reports
- **Account** (in the sidebar) → the **outstanding receivables** report: who owes
  what, and how overdue.
- From the **Dashboard → Reports** panel → **Revenue Report**: income by month,
  by service, and by client, split into **recurring vs one-time**. Export to CSV.

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
