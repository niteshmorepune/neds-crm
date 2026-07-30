<?php

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\QuarterlyAward;
use App\Models\User;

it('lets Admin/Manager view and review any award', function (UserRole $role) {
    $reviewer = User::factory()->role($role)->create();
    $award = QuarterlyAward::factory()->create();

    expect($reviewer->can('view', $award))->toBeTrue()
        ->and($reviewer->can('review', $award))->toBeTrue()
        ->and($reviewer->can('regenerate', QuarterlyAward::class))->toBeTrue();
})->with([
    'admin' => UserRole::Admin,
    'manager' => UserRole::Manager,
]);

it('forbids a regular user from reviewing or regenerating', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $award = QuarterlyAward::factory()->create();

    expect($sales->can('review', $award))->toBeFalse()
        ->and($sales->can('regenerate', QuarterlyAward::class))->toBeFalse();
});

it('lets the winner view their own approved award but not their own pending one', function () {
    $winner = User::factory()->role(UserRole::Sales)->create();
    $pending = QuarterlyAward::factory()->create(['user_id' => $winner->id, 'status' => AwardStatus::Pending, 'quarter' => 1]);
    $approved = QuarterlyAward::factory()->approved()->create(['user_id' => $winner->id, 'quarter' => 2]);

    expect($winner->can('view', $pending))->toBeFalse()
        ->and($winner->can('view', $approved))->toBeTrue();
});

it('forbids viewing someone else\'s award', function () {
    $someone = User::factory()->role(UserRole::Sales)->create();
    $award = QuarterlyAward::factory()->approved()->create();

    expect($someone->can('view', $award))->toBeFalse();
});

it('only allows downloading the certificate once approved', function () {
    $winner = User::factory()->role(UserRole::Sales)->create();
    $pending = QuarterlyAward::factory()->create(['user_id' => $winner->id, 'status' => AwardStatus::Pending, 'quarter' => 1]);
    $approved = QuarterlyAward::factory()->approved()->create(['user_id' => $winner->id, 'quarter' => 2]);

    expect($winner->can('downloadCertificate', $pending))->toBeFalse()
        ->and($winner->can('downloadCertificate', $approved))->toBeTrue();
});
