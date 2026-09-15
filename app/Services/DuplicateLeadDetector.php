<?php

namespace App\Services;

use App\Models\Lead;

/**
 * After-the-fact duplicate check for the WhatsappWebhookController::
 * handleUnmatchedNumber() blank-Lead path — see the read-only investigation
 * this was built from (memory: whatsapp-lead-goal-reask-and-duplicates /
 * the follow-on "real-time duplicate check" feasibility investigation).
 *
 * Deliberately NOT a reply gate: 2 of the 5 confirmed real duplicate pairs
 * had the WhatsApp-sourced duplicate Lead created BEFORE the real Lead it
 * duplicates even existed (13s and 26s earlier) — no lookup, at any speed,
 * can find a record that doesn't exist yet. This runs as a side effect
 * after Lead::create(), to alert staff quickly, not to prevent anything.
 *
 * Matching needs real normalization, not exact/LIKE string comparison — of
 * the 5 confirmed real pairs, only 1 was an exact string match. The other 4
 * failed on an honorific prefix ("Dr "), emoji wrapping, a missing middle
 * name, or a concatenated-vs-split first name. Verified against all 5 real
 * pairs (see tests/Unit/DuplicateLeadDetectorTest.php) before picking the
 * thresholds below.
 *
 * Cost note: this loads every Lead created in the last WINDOW_DAYS days
 * (currently a handful of rows — 388 leads have existed in this app's
 * entire history) and compares names in PHP. Fine at this scale; if lead
 * volume grows ~100x, worth pushing the initial window filter onto an
 * indexed created_at column (already indexed via `next_follow_up_at`'s
 * neighbor pattern would need a real leads.created_at index added) before
 * doing PHP-side comparison. Not needed today — a full unindexed scan over
 * the whole leads table timed at 0.5ms on production.
 */
class DuplicateLeadDetector
{
    /**
     * Matches the actual gap distribution found in the 5 confirmed real
     * pairs (13s/15s/26s/17min/7.85 days) with margin, without reaching back
     * into stale, unrelated history.
     */
    private const WINDOW_DAYS = 14;

    /**
     * Verified against the real "Neerajkumar S Pandey" / "Neeraj Kumar
     * Pandey" pair (95.7%) with a wide margin over the closest unrelated
     * same-surname sanity case tried ("Advait Kulkarni" / "Aditya
     * Kulkarni", 66.7%) — see the Unit test for the full comparison table.
     */
    private const CONCAT_SIMILARITY_THRESHOLD = 90.0;

    /**
     * Deliberately small and specific rather than a guessed exhaustive
     * list — "Dr" is the one honorific actually observed in the confirmed
     * data (Lead #233, "Dr Rahul jain"); Mr/Mrs/Ms and the Hindi/Marathi
     * equivalents Shri/Smt are the standard, unambiguous set for this
     * app's Maharashtra customer base, not a broad guess.
     */
    private const HONORIFICS = ['dr', 'mr', 'mrs', 'ms', 'shri', 'smt'];

    /**
     * Names that are structurally incapable of identifying a real person,
     * so must never be allowed to match regardless of token count — found
     * via a 2026-09-15 read-only dry-run of this exact logic against the
     * full 388-lead production history (no writes, no notifications; run
     * on request after this feature had already shipped, to validate the
     * threshold against real data beyond the 5 known pairs it was tuned
     * on). 'whatsapp inquiry' is `WhatsappWebhookController::
     * handleUnmatchedNumber()`'s own literal fallback name for a lead with
     * no WhatsApp profile name set — happens routinely (not a rare edge
     * case), and every two such leads landing within the 14-day window
     * would otherwise match each other with 100% confidence despite
     * carrying zero actual identifying signal. 5 of the dry-run's 37
     * non-known-pair matches were exactly this, the single most common
     * false-positive shape found — everything else was either internal
     * staff self-testing with their own name/number (accepted noise, a
     * human dismisses it in seconds) or a plausible genuine match. Notably,
     * the fuzzy surname+concat rule (namesMatch()'s second branch) never
     * fired on an unrelated pair even once across the full history.
     */
    private const GENERIC_PLACEHOLDER_NAMES = ['whatsapp inquiry'];

    /**
     * Finds the single best-matching, older Lead this new Lead might be a
     * duplicate of. Only considers Leads created in the window immediately
     * before this one (see WINDOW_DAYS) with a different phone number — a
     * genuinely-new Lead has nothing "after" it yet, so this is always a
     * backward-looking search. Returns null when the Lead's own name is too
     * weak a signal to match on at all (fewer than 2 tokens — see
     * normalize()'s docblock on the "Santosh" false-positive case this
     * guards against — or a generic placeholder name, see
     * GENERIC_PLACEHOLDER_NAMES).
     */
    public function findCandidate(Lead $lead): ?Lead
    {
        $normalized = self::normalize((string) $lead->name);

        if (in_array($normalized, self::GENERIC_PLACEHOLDER_NAMES, true)) {
            return null;
        }

        if (count(self::tokens($normalized)) < 2) {
            return null;
        }

        return Lead::query()
            ->where('id', '!=', $lead->id)
            ->where('phone', '!=', $lead->phone)
            ->where('created_at', '>=', $lead->created_at->copy()->subDays(self::WINDOW_DAYS))
            ->where('created_at', '<=', $lead->created_at)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'phone', 'created_at'])
            ->first(fn (Lead $candidate) => self::namesMatch($normalized, self::normalize((string) $candidate->name)));
    }

    /**
     * Strips emoji/punctuation (keeps only letters and whitespace), a
     * leading honorific, and collapses whitespace/case. Confirmed against
     * production data: 4 of the 5 real confirmed duplicate pairs fail a
     * plain string/LIKE comparison without this — "Dr Rahul jain"/"Rahul
     * Jain" and "🌹Akash Rathod🌹"/"Akash Rathod" become exact matches once
     * normalized; the other two still need namesMatch()'s fuzzy rules below.
     */
    public static function normalize(string $name): string
    {
        $clean = preg_replace('/[^\p{L}\s]/u', ' ', $name) ?? '';
        $clean = mb_strtolower(trim(preg_replace('/\s+/', ' ', $clean) ?? ''), 'UTF-8');

        $tokens = self::tokens($clean);
        if (count($tokens) > 1 && in_array($tokens[0], self::HONORIFICS, true)) {
            array_shift($tokens);
        }

        return implode(' ', $tokens);
    }

    /**
     * Both names must already be normalize()d. Requires at least 2 tokens
     * on BOTH sides — a bare single-word name ("Santosh," a real name seen
     * twice, unrelated, in production data) is too weak a signal to safely
     * flag; a coincidental exact match on a common two-word name (e.g.
     * "Suresh Kumar," also seen twice in production) is still allowed to
     * flag, since this only ever produces a human-reviewed suggestion, never
     * an automatic merge — a rep dismissing an occasional false positive in
     * seconds is a much cheaper cost than a missed real duplicate.
     *
     * Two ways to match:
     * 1. Token subset — every token of the shorter normalized name appears
     *    verbatim in the longer one. Catches an exact match, and a missing/
     *    extra middle name ("Advait Kulkarni" ⊂ "Advait Vasant Kulkarni").
     * 2. Surname-exact + fuzzy first/middle — the last token matches
     *    exactly, and the remaining tokens, concatenated with no separator,
     *    are CONCAT_SIMILARITY_THRESHOLD%+ similar (similar_text()).
     *    Catches a first name that's split in one record and concatenated
     *    in the other ("Neerajkumar S Pandey" vs "Neeraj Kumar Pandey" —
     *    "neerajkumars" vs "neerajkumar", 95.7% similar), which rule 1 alone
     *    misses. Confirmed this rule does NOT also catch a genuinely
     *    different first name sharing a surname ("Advait Kulkarni" vs
     *    "Aditya Kulkarni" scores only 66.7% on the same comparison — a wide
     *    margin below the threshold).
     */
    public static function namesMatch(string $normalizedA, string $normalizedB): bool
    {
        $tokensA = self::tokens($normalizedA);
        $tokensB = self::tokens($normalizedB);

        if (count($tokensA) < 2 || count($tokensB) < 2) {
            return false;
        }

        if (self::isTokenSubset($tokensA, $tokensB)) {
            return true;
        }

        return self::surnameMatchesWithFuzzyFirstName($tokensA, $tokensB);
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $normalized): array
    {
        return array_values(array_filter(explode(' ', $normalized), fn ($t) => $t !== ''));
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function isTokenSubset(array $a, array $b): bool
    {
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];

        foreach ($short as $token) {
            if (! in_array($token, $long, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function surnameMatchesWithFuzzyFirstName(array $a, array $b): bool
    {
        $lastA = array_pop($a);
        $lastB = array_pop($b);

        if ($lastA !== $lastB) {
            return false;
        }

        $concatA = implode('', $a);
        $concatB = implode('', $b);

        if ($concatA === '' || $concatB === '') {
            return false;
        }

        similar_text($concatA, $concatB, $percent);

        return $percent >= self::CONCAT_SIMILARITY_THRESHOLD;
    }
}
