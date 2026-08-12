# Staff FAQ

A running list of "why does it work this way" and "how do I..." questions —
pulled from things the team has actually asked, not a generic feature list.
If your question isn't here, ask in your team's group; if it comes up more
than once, it'll get added.

## 1. Navigating the CRM

**Q: I can't see a sidebar item my colleague sees — is that a bug?**
Not necessarily. What you see in the sidebar is controlled by your role
(plus any per-user grants an admin has added on top) — a hidden menu item is
a Menu Controller setting, not a sign something's broken. If you think you
should have access to something, ask an admin or manager to check it.

**Q: I hold two roles (e.g. Sales and Support) — what changes for me?**
You get the sidebar items and record access for **both** roles combined —
whichever role grants something, you have it. Your **dashboard panel** (the
stats/widgets you land on after login) still follows only your primary
role, though, so it doesn't switch depending on which role "wins."

**Q: A page I'm on doesn't highlight the matching sidebar item — why?**
That used to be a real gap (only an exact page match lit up), and it's been
fixed — a lead's detail page now correctly highlights "Lead Generation," for
example. If you spot a page that still doesn't highlight its sidebar item,
that's worth flagging.

## 2. Dashboard

**Q: What are the "Reminders" cards on my dashboard?**
Those are [Team Nudges](getting-started.md#4d-reminders-team-nudges) —
admin/manager-created reminders for the whole team or a specific role (e.g.
"log every active client as a Deal," "route every issue through Tickets").
Mark one **Done** once you've handled it, or **Snooze** it to hide it from
your own view for now — snoozing only affects what you see; the admin/
manager overview always shows the real status.

**Q: Why can I only see my own productivity rank, not the whole team's?**
By design — it's meant to help you see where you personally stand and what
to focus on, not to be a public leaderboard. Admins and managers see the
full team view on the Employee Performance Report.

**Q: Where do I find shared internal files (plugin builds, GST certificates, etc.)?**
[Resources](getting-started.md#4g-resources) in the sidebar — a Files tab
for shared internal documents and a Links tab for frequently-used external
tools/links. What you see there can be restricted by department, so you may
not see every file — that's intentional, not a bug.

## 3. Sales & Incentives

**Q: How is my monthly incentive calculated?**
Off the pre-tax **value of deals you personally won** that month — the same
figure your Sales Dashboard and the company's monthly target already use —
not off invoices or payments collected, so it always agrees with the
numbers you're already tracking. See [Incentives](sales.md#5-incentives)
for the exact slab percentages.

**Q: Can I see other reps' incentive numbers?**
No — only your own. Admin/Manager see everyone's, plus the team-bonus pool
settings.

## 4. Projects & Tasks

**Q: A task shows "Standalone" or "Project removed" instead of a project name — what does that mean?**
"Standalone" means the task genuinely was never linked to a project.
"Project removed" means it *was* linked to a real project that's since been
deleted — the task is still a valid historical record, just missing its
parent. Neither means the task itself is broken.

**Q: I'm the Project Manager on a project but couldn't see a task my own assignee created — why?**
That was a real bug and it's been fixed — visibility now checks whether
you're the project's Project Manager (Owner) as well as whether you're a
team member on it. If you still can't see a task on a project you manage,
report it again.

**Q: Why did a routine recurring task (like a weekly GMB update) land on a Sales rep instead of Support?**
That was a real bug — a Sales rep can end up as a project's default
"owner" just from having won the deal — and it's been fixed. Recurring
maintenance tasks now skip Sales entirely when picking who to assign to.

## 5. Accounts & Invoicing

**Q: Why did the Accounts dashboard and the Receivables Report used to show different "outstanding" totals?**
They used to run separate calculations that could quietly drift apart.
They now share one calculation, so the two numbers can't disagree again —
if you ever see them differ, flag it immediately rather than trusting
either one.

**Q: I entered a payment with the wrong date, mode, or reference — can I fix it without deleting it?**
Yes — open the invoice; each payment row has an edit option for **date,
mode, and reference**. The **amount** and **TDS** can't be edited in place
(they drive the invoice balance and an already-sent notification) — for
those, delete and re-record the payment.

**Q: A recurring service shows "Not Billed" — is that different from "Ended"?**
Yes. "Ended" means billing ran and finished normally. "Not Billed" means
the recurring template was switched off before it ever generated a single
invoice — a distinct, honest state so it isn't mistaken for completed
billing.

**Q: A client paid us before we'd raised a quotation or invoice — what do I do?**
Record it as a [Client Advance](accounts.md#1a-client-advances--money-received-before-a-quotation-or-invoice-exists)
rather than waiting to invoice first. It gets applied against the invoice
once one exists.

**Q: Can clients pay their invoice online?**
Yes, via Razorpay — clients see a **Pay Now** option on their portal
invoice page for unpaid/partially-paid invoices.

## 6. Tickets, Calls & Meetings

**Q: A call follow-up reminder I set just disappeared — did something go wrong?**
No — if a new call to the same client/lead was logged as **Connected** or
**Follow-up Needed**, that supersedes and clears the earlier pending
reminder automatically, so you're not chasing an already-actioned
follow-up. A failed attempt (No Answer/Busy) leaves the original reminder
standing.

**Q: How do I get notes from a Google Meet call into the CRM?**
Connect your Google account once (Profile → Connect Google Account), then
use **Import Meet Notes** on the Customer or Lead's Calls tab to pull in a
past meeting's recording/transcript link — and, if AI is enabled, an
auto-generated summary.

**Q: A client says their portal invite/reset link isn't working — what happened?**
Almost always means the link is no longer the current one — most commonly
because their portal access was revoked and re-invited since that email
was sent, which invalidates the earlier link. Have them use **Forgot your
password?** on the portal login page to get a fresh one; there's nothing
you need to do on your end.

## 7. Notifications

**Q: A notification links to an invoice but says "(invoice deleted)" — is that a bug?**
No — it means the invoice named in that notification was real and has
since been legitimately deleted (usually a cleanup or correction). The
notification stays as an accurate record of what happened at the time; it
just can't link through to a page that no longer exists.

---

Have a question that isn't answered here? Ask in your team channel — if
more than one person runs into it, it'll get added to this list.
