<?php

use App\Enums\NudgeAutoDetectType;
use App\Models\Deal;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TeamNudgeDetector;

it('DealsLoggedThisWeek is true only for a Deal the user actually owns, created within the period', function () {
    $owner = User::factory()->create();
    $periodStart = now()->startOfWeek();
    $detector = app(TeamNudgeDetector::class);

    expect($detector->check(NudgeAutoDetectType::DealsLoggedThisWeek, $owner, $periodStart))->toBeFalse();

    Deal::factory()->create(['owner_id' => $owner->id, 'created_at' => now()]);

    expect($detector->check(NudgeAutoDetectType::DealsLoggedThisWeek, $owner, $periodStart))->toBeTrue();
});

it('TicketsLoggedThisWeek is true only for a Ticket the user actually created, within the period', function () {
    $creator = User::factory()->create();
    $periodStart = now()->startOfWeek();
    $detector = app(TeamNudgeDetector::class);

    expect($detector->check(NudgeAutoDetectType::TicketsLoggedThisWeek, $creator, $periodStart))->toBeFalse();

    Ticket::factory()->create(['created_by' => $creator->id, 'created_at' => now()]);

    expect($detector->check(NudgeAutoDetectType::TicketsLoggedThisWeek, $creator, $periodStart))->toBeTrue();
});
