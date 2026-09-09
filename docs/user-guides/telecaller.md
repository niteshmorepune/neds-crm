# Telecaller guide

Welcome to the NEDS CRM. As a telecaller, your job is calling and qualifying
leads — the CRM gives you access to exactly that: **Lead Generation** and
**Calling**, plus the same daily basics everyone gets (attendance, tasks,
daily reports).

## 1. Your dashboard
When you log in, you'll see:
- **New leads to call** — how many leads currently marked New are assigned
  to **you specifically**. Every new lead is automatically routed to a
  telecaller (round-robin, same idea as how a lead routes to a Sales rep)
  the moment it's created, and Admin/Manager can also assign or reassign
  one to you by hand from the lead's own page. This used to be a shared,
  unowned queue — as of 2026-09-03 each telecaller has their own list.
- **Calls made today** — your own call count for today.
- **Follow-ups due** — how many of your own logged calls have a follow-up
  date that's arrived.
- **Your target this month** — a progress bar against your monthly **calls
  made** target, if Admin/Manager has set one for you (Team Targets page).
  "No target set" is normal if they haven't.

## 2. Attendance
Do **both** every day: use the **Check In** / **Check Out** buttons on your
dashboard, **and** punch the biometric machine when you arrive and leave —
see the Getting Started guide for how the two work together.

## 2a. Resources
**Sidebar → Resources** has two tabs — **Files** (a shared internal file
library) and **Links** (a company-wide reference list — hosting signup
links, scheduling links, and similar you can pass on to a lead). You can
view whatever's visible to your role on both, but you **cannot** add, edit,
or delete entries — that's Admin/Manager only. Some items are restricted to
specific roles, so you may not see everything others do.

## 3. Lead Generation
Under **Lead Generation** in the sidebar you see only the leads assigned to
**you** (via the lead's own "Telecaller" field — separate from its Sales
"Owner"). Every new lead is auto-routed to a telecaller the moment it's
created; Admin/Manager can also assign or move one to a different telecaller
by hand from the lead's Edit page.

**A lead that was already Converted or Lost before 2026-09-03 won't show up
here for you** — when this feature launched, only still-open leads were
backfilled with a telecaller, since a closed lead isn't part of anyone's
active calling queue. If you need to look up an old converted/lost lead
from before that date, ask Admin/Manager (they always see every lead,
regardless of Telecaller assignment).

**Going on leave with open leads?** When your manager approves your Leave
Request, they'll pick another active Telecaller to cover your leads'
WhatsApp chats on wadesk while you're out — that access starts the moment
they approve and ends on its own once your leave is over, with nothing to
undo when you're back. Your own Telecaller assignment on each lead never
changes.

Open a lead assigned to you to:
- Read its details, source, and notes.
- **Update it** — change its status (New → Contacted → Qualified, etc.), add
  a note on what was discussed, set a next follow-up date.

**"Call this lead now" popup:** the moment a new lead lands in your queue
with no call logged yet, a small card appears in the bottom-right corner of
every page, oldest uncalled lead first. Click **Log the call** to jump
straight to the Log a Call form with that lead pre-selected, or **Snooze**
if you can't call right now — pick 30 min, 2 hours, or tomorrow. If you
haven't checked in for attendance yet today, that comes first (see
Section 2 above) — once
you're checked in, this is what the same popup guides you through next.

**What to call first:** the list sorts by **Priority** by default (an
overdue follow-up first, then due today, then a Hot lead nobody's followed
up on yet, then everything else) — not newest. Switch to **Newest** via
the toggle above the list if you want plain chronological order. A red
**Overdue** or amber **Due today** badge shows right on a lead's row. The
**"Needs attention today"** strip above the list gives one-click counts
for all five — overdue, due today, Hot-but-untouched, **unresponsive**
(3+ call/WhatsApp attempts with no answer or reply, still open), and
**status may need updating** (a New lead that already has a note or call
logged against it) — these cover only the leads assigned to you, same as
the rest of this page. Tip: if you type a note
describing a call, tick **📞 This was a call** and pick an outcome in the
note box — it logs a real call AND moves the lead off New automatically,
so it never shows up in that last count. Open an unresponsive lead and
you'll see a **"📵 Not responding — next best action"** box telling you
what to try next (switch channels, or the best time to call if you've
only tried calls). For a lead already flagged (New with real activity, or
unresponsive), open it and click **✨ Suggest a status** — AI reads its
notes/calls and suggests Contacted/Qualified/Lost with a reason; pick
from the dropdown (overridable) and click **Apply**.

You **cannot create a brand-new lead** from scratch, **convert** a lead into
a client/deal, or **delete** a lead — those stay with Sales/Manager/Admin.
When a lead is genuinely ready to move forward, hand it to the assigned
Sales rep (shown on the lead) rather than converting it yourself.

**Replying to a lead over WhatsApp:** if a lead came in over WhatsApp (the
marketing number), you can reply to them without leaving the CRM — open the
lead, write your note, and tick the **"Also send as WhatsApp reply"**
checkbox before saving (it only appears when the lead has an open WhatsApp
conversation). Leave it unchecked for a normal internal note — that's the
default, so nothing goes to the client unless you explicitly tick it.

**If a lead asks to pay while chatting on WhatsApp (e.g. "send me the QR
code"), don't try to paste a payment link or image into the chat** — if
WhatsApp's 24-hour reply window has closed since their last message, it
will silently fail to deliver, and there's no way around that from here
(a WhatsApp platform rule, not a CRM limit — you also won't see an error,
it just never arrives). Since Quotations aren't available to Telecaller,
flag it to the lead's owner (Sales) — they can create a real Quotation and
click **Send to Client**, which goes out as an approved WhatsApp template
that reaches the client even with the window closed, with a working **Pay
Now** button.

**Visibility Audit Funnel:** **Lead Generation → VA Recovery** shows the
whole Meta Ads → offer page → checkout → paid journey for GMB-tagged
leads, plus a queue of who's stuck at which stage — worth a follow-up
call. The CRM automatically WhatsApps a first invite as soon as one of
these leads comes in (Meta's own form never sends them anywhere), then
nudges again a few hours later if they stall, so someone already on this
list may convert before you even get to them. A row still stuck at
checkout or the offer page also gets a green **WhatsApp →** button next
to "Open lead" — it jumps straight into that lead's wadesk chat with the
matching recovery template already picked and filled in, ready for you
to review and send. Below that whole-team queue, **Your gaps** and
**Your message log** show just your own leads —
who's stuck or missing a service tag, and every AI-WhatsApp send to your
own leads. A lead's own page also shows a colored **"Visibility Audit:"**
badge right under its name, so opening the lead directly tells you the
same thing this queue does.

Reaching checkout or paying re-scores the lead's AI score immediately, so
it also rises in the Priority sort above — the two lists stay in sync
without you needing to check both.

## 4. Calling
Use **☎ Log a call** (top bar) or **Calling** to record every call you make.
Pick the **Lead** you called, the **direction**, **outcome**, and any notes —
set a **follow-up date** if you need to call back. Your own call history is
under **Calling** in the sidebar. Logging a call against a brand-new
(**New**) lead automatically moves its status to **Contacted** for you.
Logged one by mistake? A **Delete** link appears next to it — you can
remove your own, or a manager/admin can remove anyone's.

**Best time to call:** open any lead and you'll see a **📞 Best time to
call** box above its call history — every attempt already made to that
lead (when, and what happened), plus a recommended hour band for your next
try. Any hour already tried twice with no answer is left out of the
recommendation; if every usually-good hour has already failed for this
particular lead, it says so rather than repeating a suggestion that hasn't
worked. A brand-new lead with no calls yet, from Website/WhatsApp/Meta
Ads/Phone Enquiry, instead gets a suggestion built around **the actual
time they reached out** — more specific than the general team pattern,
since that's a real signal about when this particular person is around
(a Cold Call lead doesn't get this — that timestamp is just data entry,
not the prospect's own timing). The same recommendation shows as a short
**"Try: …"** badge right on the Lead Generation list and in My Day, so
you know before you even open the lead.

The moment a follow-up you set becomes due, the "what to do next" popup
(see Section 2 above) prompts you with it — whatever you typed in as the
next action, and a **Log the call** button that jumps straight to the form
pre-filled for that lead. Snooze it — 30 min, 2 hours, or tomorrow — if
you're not ready yet. Logging a Connected call with no follow-up date
shows a small amber reminder before you save — not a hard stop, just a
nudge in case you meant to set one.

**Stalling on something?** A lead's own page, and the Log a Call form
when a lead is selected, both have a **"Stalling on:"** dropdown — Budget,
Went with a competitor, Trust/credibility, Didn't understand the offer, or
Awaiting their decision. Tag it the moment a real conversation stops
moving forward. If nobody touches that lead again for 3 days, the "what
to do next" popup brings it back up by name instead of a generic
reminder. Leaving the dropdown blank on the call form never clears a tag
already set on the lead's own page. **Sidebar → Stalling** lists every
lead you've tagged this way, most-quiet-first — a single place to see
your whole "stuck" list. If a tagged lead then goes 7 days with no note,
call, or edit from you, AI drafts a check-in addressing that specific
reason (e.g. Budget gets an offer to discuss a staged plan) and notifies
you — review and send it yourself, same as any other AI draft.

**Their goal, and their Website/GBP link:** a lead's own page and the Log a
Call form both have a **"What's their biggest goal?"** picker — Generate
More Leads, Rank Higher on Google, Grow My Business Online, or Not Sure –
Need Expert Advice. Ask this on the call and set it — a Meta Ads lead may
already have it pre-filled from their form answer. If they pick one of the
first three, ask for their **Website URL** or **Google Business Profile
link** on the same or next call and save it right there (a blue note on
the lead reminds you until one's captured). If they pick **Not Sure**, a
purple note tells you to hand them off for a call with a **Sales Expert**
— use **Create Meeting** on the lead's page to schedule it. Leaving the
goal/link fields blank on the Log a Call form never clears what's already
saved — same rule as Stalling on. **This can now also get answered on
WhatsApp before you ever call** — if a lead messages our Marketing number
after hours, the assistant asks this same question (a real tappable
list) and follows the same branching itself, so you may find it's
already filled in by the time you open the lead.

**Every fresh Meta Ads lead also gets an automatic WhatsApp welcome**
(except GMB-tagged ones, who get the Visibility Audit invite instead) —
a thank-you plus "what time works for a quick call?" the moment the lead
is created, specifically to get them to reply so WhatsApp's 24-hour
window opens up. If one of your leads has gone quiet and that window's
closed, open the lead and click **📱 Send WhatsApp check-in** to send a
re-engagement template and try again — limited to once every 24 hours
per lead. **You don't have to remember, either** — after 6 hours with no
reply, the CRM sends this same check-in automatically, once, and a
**💬 Welcome sent, no reply** badge shows on the lead's row (and in the
Needs Attention strip) so you know to call, not just wait on WhatsApp.

## 5. Daily report
At the end of each working day, open **Daily Reports** and fill in a brief
summary of your day. Some numbers (calls made) are filled in automatically.
Submit it before leaving. You'll get a reminder email at 6 PM.

This page also shows a **⏳ Carried forward** panel for anything left over
from before today, a **✅ Completed today** list with how long each task
took, a **Still pending** panel (everything still open for you across every
module), an **Activity timeline** of everything you did (browsable to a
previous day), and (once submitted) a **📋 Copy to send** button to paste
your report into WhatsApp — see [Getting Started → Daily
report](getting-started.md) for the full rundown.

## 6. What you can't access
The following modules are not available to telecallers: Deals, Quotations,
Invoices, Incentives, Tickets, and Clients — those belong to Sales, Accounts,
and Support. If a lead needs a quotation or is ready to become a client,
that's the assigned Sales rep's next step, not yours.
