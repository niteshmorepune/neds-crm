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
