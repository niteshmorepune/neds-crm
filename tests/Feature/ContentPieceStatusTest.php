<?php

use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;

it('agency_led workflow has correct status transitions', function () {
    $map = ContentStatus::allowedNextFor(ContentWorkflowType::AgencyLed);

    expect($map[ContentStatus::PendingFromAgency->value])->toBe([ContentStatus::Received->value]);
    expect($map[ContentStatus::Received->value])->toBe([ContentStatus::Approved->value]);
    expect($map[ContentStatus::Approved->value])->toBe([ContentStatus::Scheduled->value]);
    expect($map[ContentStatus::Scheduled->value])->toBe([ContentStatus::Published->value]);
    expect($map[ContentStatus::Published->value])->toBe([]);
});

it('neds_led workflow has correct status transitions', function () {
    $map = ContentStatus::allowedNextFor(ContentWorkflowType::NedsLed);

    expect($map[ContentStatus::CopyDrafting->value])->toBe([ContentStatus::SentToPartner->value]);
    expect($map[ContentStatus::SentToPartner->value])->toBe([ContentStatus::Received->value]);
    expect($map[ContentStatus::Received->value])->toBe([ContentStatus::Approved->value]);
    expect($map[ContentStatus::Approved->value])->toBe([ContentStatus::Scheduled->value]);
    expect($map[ContentStatus::Scheduled->value])->toBe([ContentStatus::Published->value]);
    expect($map[ContentStatus::Published->value])->toBe([]);
});

it('initial status for agency_led is pending_from_agency', function () {
    expect(ContentStatus::initialFor(ContentWorkflowType::AgencyLed))
        ->toBe(ContentStatus::PendingFromAgency);
});

it('initial status for neds_led is copy_drafting', function () {
    expect(ContentStatus::initialFor(ContentWorkflowType::NedsLed))
        ->toBe(ContentStatus::CopyDrafting);
});
