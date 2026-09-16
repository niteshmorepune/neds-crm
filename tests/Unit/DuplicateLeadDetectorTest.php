<?php

use App\Services\DuplicateLeadDetector;

// ──────────────────────────────────────────────────────────────────────────
// The 5 confirmed real duplicate pairs from the prior investigation — the
// actual test fixture this threshold was picked against, not hypotheticals.
// Real production name strings, real pair, must all match once normalized.
// ──────────────────────────────────────────────────────────────────────────

it('matches all 5 confirmed real duplicate pairs after normalization', function (string $a, string $b) {
    $na = DuplicateLeadDetector::normalize($a);
    $nb = DuplicateLeadDetector::normalize($b);

    expect(DuplicateLeadDetector::namesMatch($na, $nb))->toBeTrue("Expected '{$a}' to match '{$b}'");
})->with([
    'Lead #233/#234 (honorific prefix)' => ['Dr Rahul jain', 'Rahul Jain'],
    'Lead #236/#237 (emoji wrapping)' => ['🌹Akash Rathod🌹', 'Akash Rathod'],
    'Lead #257/#258 (exact match)' => ['Keshav Dhane', 'Keshav Dhane'],
    'Lead #356/#357 (missing middle name)' => ['Advait Vasant Kulkarni', 'Advait Kulkarni'],
    'Lead #346/#411 (concatenated vs split first name)' => ['Neerajkumar S Pandey', 'Neeraj Kumar Pandey'],
]);

it('is symmetric — order of the two names never changes the result', function (string $a, string $b) {
    $na = DuplicateLeadDetector::normalize($a);
    $nb = DuplicateLeadDetector::normalize($b);

    expect(DuplicateLeadDetector::namesMatch($nb, $na))->toBeTrue();
})->with([
    ['Dr Rahul jain', 'Rahul Jain'],
    ['🌹Akash Rathod🌹', 'Akash Rathod'],
    ['Advait Vasant Kulkarni', 'Advait Kulkarni'],
    ['Neerajkumar S Pandey', 'Neeraj Kumar Pandey'],
]);

// ──────────────────────────────────────────────────────────────────────────
// normalize() unit behavior
// ──────────────────────────────────────────────────────────────────────────

it('strips a leading honorific', function () {
    expect(DuplicateLeadDetector::normalize('Dr Rahul jain'))->toBe('rahul jain');
    expect(DuplicateLeadDetector::normalize('Mrs Seema Mourya'))->toBe('seema mourya');
});

it('does not strip an honorific-like word that is not actually a prefix on its own', function () {
    // "Mr" only strips when it's the leading token — a name that merely
    // contains "mr" as a substring of a real word must survive untouched.
    expect(DuplicateLeadDetector::normalize('Amresh Patil'))->toBe('amresh patil');
});

it('never reduces a single-word name to nothing by stripping its only token as an honorific', function () {
    // A bare "Dr" with nothing else would be a degenerate case; the guard
    // (count($tokens) > 1) means a lone token is never stripped.
    expect(DuplicateLeadDetector::normalize('Dr'))->toBe('dr');
});

it('strips emoji and punctuation, keeping only letters and spaces', function () {
    expect(DuplicateLeadDetector::normalize('🌹Akash Rathod🌹'))->toBe('akash rathod');
    expect(DuplicateLeadDetector::normalize('Akash-Rathod!!'))->toBe('akash rathod');
});

it('lowercases and collapses repeated whitespace', function () {
    expect(DuplicateLeadDetector::normalize('  KESHAV   Dhane  '))->toBe('keshav dhane');
});

// ──────────────────────────────────────────────────────────────────────────
// False-positive risk — the two real name collisions found in production
// data during the investigation, considered deliberately, not left
// unconsidered as edge cases.
// ──────────────────────────────────────────────────────────────────────────

it('does NOT match a bare single-word name, even an exact one — too weak a signal ("Santosh" x2, real production collision)', function () {
    $na = DuplicateLeadDetector::normalize('Santosh');
    $nb = DuplicateLeadDetector::normalize('Santosh');

    expect(DuplicateLeadDetector::namesMatch($na, $nb))->toBeFalse();
});

it('DOES match an exact two-token name collision ("Suresh Kumar" x2, real production collision) — accepted deliberately since this only ever produces a human-reviewed suggestion, never an automatic merge', function () {
    $na = DuplicateLeadDetector::normalize('Suresh Kumar');
    $nb = DuplicateLeadDetector::normalize('Suresh Kumar');

    expect(DuplicateLeadDetector::namesMatch($na, $nb))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────
// Unrelated-name sanity checks — must NOT match, including the closest
// same-surname-different-first-name case, which scores close to (and for
// raw similar_text(), even higher than) a real pair on a naive metric.
// ──────────────────────────────────────────────────────────────────────────

it('does not match two different people who merely share a surname', function (string $a, string $b) {
    $na = DuplicateLeadDetector::normalize($a);
    $nb = DuplicateLeadDetector::normalize($b);

    expect(DuplicateLeadDetector::namesMatch($na, $nb))->toBeFalse("Expected '{$a}' to NOT match '{$b}'");
})->with([
    'different first name, same surname' => ['Advait Kulkarni', 'Aditya Kulkarni'],
    'different second name, same first name' => ['Rahul Jain', 'Rahul Sharma'],
    'similar-sounding but different first name, same surname' => ['Keshav Dhane', 'Kedar Dhane'],
]);

it('does not match two clearly unrelated full names', function () {
    $na = DuplicateLeadDetector::normalize('Priya Shah');
    $nb = DuplicateLeadDetector::normalize('Ramesh Gaikwad');

    expect(DuplicateLeadDetector::namesMatch($na, $nb))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────
// companyNameMatch() — the 5 real confirmed company<->name blind-spot pairs
// from the 2026-09-16 investigation (memory: backlog.md). None of these are
// catchable by namesMatch() alone — that's the whole point of this rule.
// ──────────────────────────────────────────────────────────────────────────

it('matches 4 of the 5 confirmed real company<->name duplicate pairs after normalization — the 5th (Ayushmaan Enterprises) is deliberately excluded by the significant-token-count guard below', function (string $company, string $name) {
    $nCompany = DuplicateLeadDetector::normalize($company);
    $nName = DuplicateLeadDetector::normalize($name);

    expect(DuplicateLeadDetector::companyNameMatch($nCompany, $nName))
        ->toBeTrue("Expected company '{$company}' to match name '{$name}'");
})->with([
    'Lead #104/#332 (NSS Business Group — exact business name; "business" deliberately stays out of the stoplist so this keeps 2 significant tokens)' => [
        'NSS Business Group', 'NSS BUSINESS GROUP',
    ],
    'Lead #177/#178 (Samarth Mobile Motor Controller — WhatsApp-relayed form data)' => [
        'Samarth mobile motor controller', 'samarth mobile motor controller',
    ],
    'Lead #293/#294 (Jagdamba Electricals — near-identical business name, different phones)' => [
        'Jagdamba Electricals & Repairs', 'Jagdamba Electricals and Repairs',
    ],
    'Lead #436/#437 (Leisure Pools/Fulgado Stanley — different phones)' => [
        'LEISURE POOLS, Karjat. Maharashtra', 'Leisure Pools 2',
    ],
]);

it('companyNameMatch is symmetric — order of the two arguments never changes the result', function (string $company, string $name) {
    $nCompany = DuplicateLeadDetector::normalize($company);
    $nName = DuplicateLeadDetector::normalize($name);

    expect(DuplicateLeadDetector::companyNameMatch($nName, $nCompany))->toBeTrue();
})->with([
    ['NSS Business Group', 'NSS BUSINESS GROUP'],
    ['Jagdamba Electricals & Repairs', 'Jagdamba Electricals and Repairs'],
    ['LEISURE POOLS, Karjat. Maharashtra', 'Leisure Pools 2'],
]);

// ──────────────────────────────────────────────────────────────────────────
// Significant-token-count guard — added AFTER a live, read-only dry-run
// against all 287 non-trashed production leads (2026-09-16, post-merge)
// found this was a real, recurring false-positive shape, not a rare
// hypothetical: 2 of the 3 real matches the rule produced on live data were
// exactly this ("Pawar Enterprises" false-matching an unrelated "Swaraj
// Pawar," "Vinod Mehandi Artis" false-matching an unrelated lead named just
// "Vinod") — both a common Indian name/surname surviving generic-word
// stripping down to one token, which then subset-matched into an unrelated
// lead. Only 1 of 3 real matches was genuine (Leisure Pools/Fulgado
// Stanley, which keeps 2 significant tokens and is unaffected). This is a
// deliberate recall-for-precision trade, not an oversight — the Ayushmaan
// Enterprises pair (#59/#89) is the one real confirmed pair this costs.
// ──────────────────────────────────────────────────────────────────────────

it('does NOT match when stripping generic words leaves only ONE significant token on either side, even a genuinely real pair — Ayushmaan Enterprises, traded away for precision after the dry-run findings above', function () {
    $company = DuplicateLeadDetector::normalize('Ayushmaan Enterprises – Water Purifier Ro Sales and Services in Mumbai');
    $name = DuplicateLeadDetector::normalize('Ayushmaan Enterprises');

    expect(DuplicateLeadDetector::companyNameMatch($company, $name))->toBeFalse();
});

it('does not match a common surname surviving generic-suffix-stripping against an unrelated lead — real production false positive, "Pawar Enterprises" vs "Swaraj Pawar" (#213/#199)', function () {
    $company = DuplicateLeadDetector::normalize('Pawar Enterprises');
    $name = DuplicateLeadDetector::normalize('Swaraj Pawar');

    expect(DuplicateLeadDetector::companyNameMatch($company, $name))->toBeFalse();
});

it('does not match a common first name surviving as the sole candidate token against an unrelated company — real production false positive, "Vinod Mehandi Artis" vs "Vinod" (#171/#152)', function () {
    $company = DuplicateLeadDetector::normalize('Vinod Mehandi Artis');
    $name = DuplicateLeadDetector::normalize('Vinod');

    expect(DuplicateLeadDetector::companyNameMatch($company, $name))->toBeFalse();
});

it('never matches on a shared generic business-suffix word alone — two unrelated companies both just called "X Enterprises"', function () {
    $companyA = DuplicateLeadDetector::normalize('Sunrise Enterprises');
    $companyB = DuplicateLeadDetector::normalize('Moonlight Enterprises');

    expect(DuplicateLeadDetector::companyNameMatch($companyA, $companyB))->toBeFalse();
});

it('never matches when stripping generic words empties one side entirely — a company literally named just "Enterprises"', function () {
    $bareGeneric = DuplicateLeadDetector::normalize('Enterprises');
    $realBusiness = DuplicateLeadDetector::normalize('Ayushmaan Enterprises');

    expect(DuplicateLeadDetector::companyNameMatch($bareGeneric, $realBusiness))->toBeFalse();
});

it('does not match two unrelated companies sharing only a generic multi-word suffix combination', function () {
    $companyA = DuplicateLeadDetector::normalize('Kohinoor Traders and Services');
    $companyB = DuplicateLeadDetector::normalize('Everest Traders and Services');

    expect(DuplicateLeadDetector::companyNameMatch($companyA, $companyB))->toBeFalse();
});

it('still matches two genuinely identical business names that happen to both carry a generic suffix', function () {
    $companyA = DuplicateLeadDetector::normalize('Morya Cab Services');
    $companyB = DuplicateLeadDetector::normalize('Morya Cab Services');

    expect(DuplicateLeadDetector::companyNameMatch($companyA, $companyB))->toBeTrue();
});

it('does not match two clearly unrelated company/name pairs', function () {
    $company = DuplicateLeadDetector::normalize('Priya Shah Boutique');
    $name = DuplicateLeadDetector::normalize('Ramesh Gaikwad');

    expect(DuplicateLeadDetector::companyNameMatch($company, $name))->toBeFalse();
});
