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
     * Generic business-suffix words that must never, by themselves, drive a
     * company<->name match — calibrated against a real word-frequency count
     * over every non-null `company` value in production (2026-09-16
     * investigation): "enterprises" alone appeared 26 times across
     * otherwise-unrelated companies, "services"/"service" 14,
     * "solutions"/"solution" 7, "ltd" 9, "pvt" 7, "group" 5, "business" 5 —
     * exactly the shape of word that would make two unrelated businesses
     * ("Sunrise Enterprises" / "Moonlight Enterprises") match on the suffix
     * alone if it weren't stripped before comparison. "traders"/
     * "industries" weren't in this dataset's top words but are common
     * enough Indian business-name suffixes to include pre-emptively.
     * Stripped from BOTH sides before companyNameMatch() runs its
     * comparison — see that method's own docblock for what happens if
     * stripping empties a side entirely.
     *
     * @var list<string>
     */
    private const GENERIC_BUSINESS_WORDS = [
        'enterprise', 'enterprises',
        'service', 'services',
        'solution', 'solutions',
        'group', 'traders', 'industries', 'business', 'ltd', 'pvt',
    ];

    /**
     * Finds the single best-matching, older Lead this new Lead might be a
     * duplicate of. Only considers Leads created in the window immediately
     * before this one (see WINDOW_DAYS) with a different phone number — a
     * genuinely-new Lead has nothing "after" it yet, so this is always a
     * backward-looking search. Returns null when the Lead's own name is too
     * weak a signal to match on at all (fewer than 2 tokens — see
     * normalize()'s docblock on the "Santosh" false-positive case this
     * guards against — or a generic placeholder name, see
     * GENERIC_PLACEHOLDER_NAMES). This 2-token/placeholder gate is
     * deliberately left exactly as it was — it only ever governs the new
     * Lead's own `name` field; company<->name matching (see
     * isDuplicateCandidate()) is a fully additive second path evaluated
     * per-candidate below, not a change to this gate.
     */
    public function findCandidate(Lead $lead): ?Lead
    {
        $normalizedName = self::normalize((string) $lead->name);

        if (in_array($normalizedName, self::GENERIC_PLACEHOLDER_NAMES, true)) {
            return null;
        }

        if (count(self::tokens($normalizedName)) < 2) {
            return null;
        }

        $normalizedCompany = self::normalizedOrNull($lead->company);

        return Lead::query()
            ->where('id', '!=', $lead->id)
            ->where('phone', '!=', $lead->phone)
            ->where('created_at', '>=', $lead->created_at->copy()->subDays(self::WINDOW_DAYS))
            ->where('created_at', '<=', $lead->created_at)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'company', 'phone', 'created_at'])
            ->first(fn (Lead $candidate) => self::isDuplicateCandidate($normalizedName, $normalizedCompany, $candidate));
    }

    /**
     * Three independent checks, first match wins — name<->name (unchanged
     * from before this build), then the two directions of the new
     * company<->name path (Lead A's company vs Lead B's name, and vice
     * versa). Deliberately no company<->company check — not asked for, and
     * two Leads sharing a company legitimately happens (two contacts at the
     * same real business, e.g. two people from the same office messaging
     * separately) in a way two people sharing an entire full name doesn't.
     */
    private static function isDuplicateCandidate(string $normalizedName, ?string $normalizedCompany, Lead $candidate): bool
    {
        $candidateNormalizedName = self::normalize((string) $candidate->name);

        if (self::namesMatch($normalizedName, $candidateNormalizedName)) {
            return true;
        }

        // Lead's own company <-> candidate's name. The candidate's name
        // still needs the same generic-placeholder guard findCandidate()
        // already applies to the Lead's own name — a candidate's literal
        // "WhatsApp Inquiry" fallback name is exactly as weak a signal here
        // as it is in the name<->name path.
        if ($normalizedCompany !== null
            && ! in_array($candidateNormalizedName, self::GENERIC_PLACEHOLDER_NAMES, true)
            && self::companyNameMatch($normalizedCompany, $candidateNormalizedName)
        ) {
            return true;
        }

        // Candidate's company <-> Lead's own name. $normalizedName already
        // passed the placeholder/2-token gate in findCandidate() before the
        // query ever ran, so no re-check is needed on this side.
        $candidateNormalizedCompany = self::normalizedOrNull($candidate->company);

        return $candidateNormalizedCompany !== null
            && self::companyNameMatch($normalizedName, $candidateNormalizedCompany);
    }

    /**
     * Cross-field variant of namesMatch() — compares one Lead's
     * normalize()d `company` against another Lead's normalize()d `name`.
     * The two comparison rules below (reused verbatim from namesMatch(),
     * not a third, different similarity algorithm) are already symmetric
     * in which argument represents "company" vs "name", so callers never
     * need to try both orderings of the same pair.
     *
     * Deliberately does NOT require 2+ raw tokens on both sides the way
     * namesMatch() does for person names. A company name is often a
     * proper-noun-plus-generic-suffix ("Ayushmaan Enterprises") that
     * reduces to a single significant token once the suffix is stripped —
     * requiring 2 tokens post-strip would silently exclude exactly the
     * real confirmed pairs this was built for (see
     * DuplicateLeadDetectorTest). The generic-word strip below is what
     * keeps this safe instead: if stripping empties either side, there is
     * no real signal left and no match is attempted — a bare "Enterprises"
     * == "Enterprises" can never fire on its own, regardless of how short
     * either original string was.
     */
    public static function companyNameMatch(string $normalizedA, string $normalizedB): bool
    {
        $significantA = self::stripGenericBusinessWords(self::tokens($normalizedA));
        $significantB = self::stripGenericBusinessWords(self::tokens($normalizedB));

        if ($significantA === [] || $significantB === []) {
            return false;
        }

        if (self::isTokenSubset($significantA, $significantB)) {
            return true;
        }

        return self::surnameMatchesWithFuzzyFirstName($significantA, $significantB);
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private static function stripGenericBusinessWords(array $tokens): array
    {
        return array_values(array_filter($tokens, fn (string $t) => ! in_array($t, self::GENERIC_BUSINESS_WORDS, true)));
    }

    /**
     * null for a blank/whitespace-only company (very common — most Leads
     * have no company at all) or one that normalize()s down to nothing
     * (e.g. punctuation-only). Callers treat null as "nothing to compare."
     */
    private static function normalizedOrNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = self::normalize($value);

        return $normalized === '' ? null : $normalized;
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
