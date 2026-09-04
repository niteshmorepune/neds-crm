<?php

use App\Support\ServiceTaskMatcher;

it('matches a canonical name against itself', function () {
    expect(ServiceTaskMatcher::matches('SEO', ['SEO']))->toBeTrue();
});

it('matches a renamed GMB service against the canonical "GMB" template entry', function () {
    expect(ServiceTaskMatcher::matches('GMB Services', ['GMB']))->toBeTrue();
});

it('matches a renamed Social Media service against the canonical "Social Media" template entry', function () {
    expect(ServiceTaskMatcher::matches('Social Media Management', ['Social Media']))->toBeTrue();
});

it('matches a renamed AMC Service against the canonical "AMC Service" template entry', function () {
    expect(ServiceTaskMatcher::matches('Annual Maintenance Services', ['AMC Service']))->toBeTrue();
});

it('still matches the original pre-rename name for a service with a known alias', function () {
    expect(ServiceTaskMatcher::matches('GMB', ['GMB']))->toBeTrue();
});

it('does not match an unrelated service name', function () {
    expect(ServiceTaskMatcher::matches('SEO', ['GMB']))->toBeFalse();
});

it('does not match an empty service name', function () {
    expect(ServiceTaskMatcher::matches('', ['GMB']))->toBeFalse();
});

it('matches against a multi-service template list', function () {
    $services = ['Website Design & Development', 'Software Development', 'AMC Service'];

    expect(ServiceTaskMatcher::matches('Annual Maintenance Services', $services))->toBeTrue();
    expect(ServiceTaskMatcher::matches('Software Development', $services))->toBeTrue();
    expect(ServiceTaskMatcher::matches('SEO', $services))->toBeFalse();
});

it('does not fuzzy-match an unrelated name that happens to contain the canonical name', function () {
    // 'GMB Services' is a known alias, but a hypothetical unrelated future
    // service like 'GMB Consulting' should NOT silently match.
    expect(ServiceTaskMatcher::matches('GMB Consulting', ['GMB']))->toBeFalse();
});
