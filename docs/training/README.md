# CRM training video scripts

These are **recording scripts**, not the training videos themselves — nobody
has recorded anything yet. Each file below is a scene-by-scene guide (what to
click, what to say) so whoever records can do it in one sitting without
prep work or a written outline of their own.

## Why scripts instead of finished videos

With a 10-person team, producing polished edited videos is a lot of effort
for not much payoff. A script you read while screen-recording gets you real
training video, fast, without needing editing skills — and it stays easy to
re-record whenever a workflow changes, since you just re-read the updated
section instead of re-editing footage.

## How to record

1. **Tool:** [Loom](https://loom.com) (free tier is fine, one-click screen +
   webcam + mic) or OBS Studio if you'd rather save the file locally first.
   No editing needed — Loom uploads and gives you a shareable link
   immediately.
2. **Log in as a real (or test) user** with the right role before you start,
   and have some realistic data ready to click through (an existing lead,
   client, invoice, etc. — see each script's "Before you record" checklist).
3. **Read each SAY line naturally** — it's a script, not a word-for-word
   transcript requirement. Paraphrase in your own words if that feels more
   natural; the point is to hit the same points in the same order.
4. **Don't stop and restart for small mistakes.** If you fumble a click,
   just say "let me try that again," redo it, and keep rolling — viewers
   skip through small stumbles fine, and it saves you from re-recording the
   whole thing.
5. **One take per script is the goal.** These are sized to be recordable in
   a single sitting (see the target length at the top of each file).
6. When done, drop the Loom link in the relevant training doc (or wherever
   your team keeps shared links) so new hires can find it alongside the
   written guide.

## Which script for which role

| Script | Who watches it | Target length |
|---|---|---|
| [`getting-started.md`](getting-started.md) | **Everyone**, first video watched | ~8 min |
| [`sales.md`](sales.md) | Sales | ~13 min |
| [`support.md`](support.md) | Support | ~9 min |
| [`accounts.md`](accounts.md) | Accounts | ~13 min |
| [`manager.md`](manager.md) | Manager | ~15 min |
| [`admin.md`](admin.md) | Admin | ~10 min |
| [`intern.md`](intern.md) | Intern | ~5 min |

Everyone watches `getting-started.md` first, then the one script matching
their role. Managers/admins may also want to skim the Sales/Support/Accounts
scripts since they oversee those modules, but it's not required.

**On the longer scripts (Sales, Accounts, Manager, now 13-15 min):** these
grew a lot in the 2026-07-19 refresh — a lot has shipped since these were
first written. Still recordable in one sitting, but if 13-15 minutes feels
like too much to hold in one take, it's fine to split at a natural scene
boundary (e.g. record Manager's Reports scenes as its own clip) rather than
force it into one video — two clean videos beat one long one with a lot of
restarts.

## PDF handouts

For sharing with the team (not everyone wants to read raw Markdown on
GitHub), each script also has a PDF version in `pdf/`. Regenerate all of
them after any edit:

```
npm run training-pdfs
```

Needs Node + a Chromium-based browser (Chrome or Edge) installed; override
with `CHROME_BIN=/path/to/chrome` if it isn't auto-detected. Reuses the
same Markdown→PDF pipeline as the `docs/user-guides` handouts.

## Keeping these in sync

These scripts mirror `docs/user-guides/*.md` structure closely on purpose —
if a guide changes (a new feature ships, a workflow changes), check whether
the matching training script needs the same update. They don't need to move
in lockstep automatically — outdated video is a minor inconvenience, not a
correctness problem, but try not to let them drift for more than a
release or two.
