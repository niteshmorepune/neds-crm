<?php

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\QuarterlyAward;
use App\Models\User;
use App\Services\QuarterlyAwardGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.enabled' => false]); // most tests don't care about the citation text
    Carbon\Carbon::setTestNow(Carbon\Carbon::create(2026, 8, 15)); // FY2026-27 Q2
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

it('creates one department award for a role with 2+ eligible people, and a company-wide award', function () {
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->count(3)->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    $awards = app(QuarterlyAwardGenerator::class)->generate('2026-27', 2);

    $sales = $awards->firstWhere('department', UserRole::Sales->value);
    $company = $awards->firstWhere('department', QuarterlyAward::COMPANY_WIDE);

    expect($sales)->not->toBeNull()
        ->and($sales->user_id)->toBe($alice->id)
        ->and($sales->status)->toBe(AwardStatus::Pending)
        ->and($company)->not->toBeNull()
        ->and($company->user_id)->toBe($alice->id);
});

it('does not create an award for a department with fewer than 2 eligible people', function () {
    User::factory()->role(UserRole::Accounts)->create();

    $awards = app(QuarterlyAwardGenerator::class)->generate('2026-27', 2);

    expect($awards->firstWhere('department', UserRole::Accounts->value))->toBeNull();
});

it('picks the true cross-department max for the company-wide award', function () {
    $topSales = User::factory()->role(UserRole::Sales)->create(['name' => 'Top Sales']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Low Sales']);
    Lead::factory()->count(5)->create(['owner_id' => $topSales->id, 'converted_at' => now()]);

    $topSupport = User::factory()->role(UserRole::Support)->create(['name' => 'Top Support']);
    User::factory()->role(UserRole::Support)->create(['name' => 'Low Support']);

    $awards = app(QuarterlyAwardGenerator::class)->generate('2026-27', 2);
    $company = $awards->firstWhere('department', QuarterlyAward::COMPANY_WIDE);
    $salesWinner = $awards->firstWhere('department', UserRole::Sales->value);

    // The company-wide winner must be whichever department candidate scored
    // highest overall, not an independent/different calculation.
    expect($company->user_id)->toBe($salesWinner->user_id)
        ->and($company->score)->toBe($salesWinner->score);
});

it('does not overwrite an already-approved award on regenerate, but does refresh a pending one', function () {
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->count(3)->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    $first = app(QuarterlyAwardGenerator::class)->generate('2026-27', 2);
    $salesAward = $first->firstWhere('department', UserRole::Sales->value);
    $companyAward = $first->firstWhere('department', QuarterlyAward::COMPANY_WIDE);

    $manager = User::factory()->role(UserRole::Manager)->create();
    $salesAward->update(['status' => AwardStatus::Approved, 'reviewed_by' => $manager->id, 'reviewed_at' => now()]);
    $originalUpdatedAt = $salesAward->updated_at;

    // New data that would change who the top Sales scorer is.
    $carol = User::factory()->role(UserRole::Sales)->create(['name' => 'Carol']);
    Lead::factory()->count(10)->create(['owner_id' => $carol->id, 'converted_at' => now()]);

    $second = app(QuarterlyAwardGenerator::class)->generate('2026-27', 2);
    $salesAfter = $second->firstWhere('department', UserRole::Sales->value);
    $companyAfter = $second->firstWhere('department', QuarterlyAward::COMPANY_WIDE);

    expect($salesAfter->id)->toBe($salesAward->id)
        ->and($salesAfter->user_id)->toBe($alice->id) // untouched, still the original approved winner
        ->and($salesAfter->status)->toBe(AwardStatus::Approved)
        ->and($salesAfter->updated_at->eq($originalUpdatedAt))->toBeTrue()
        ->and($companyAfter->user_id)->toBe($carol->id) // company-wide was still Pending, so it refreshed
        ->and($companyAfter->status)->toBe(AwardStatus::Pending);
});

it('drafts a citation via AI when enabled, matched back by department', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    $alice = User::factory()->role(UserRole::Sales)->create(['name' => 'Alice']);
    User::factory()->role(UserRole::Sales)->create(['name' => 'Bob']);
    Lead::factory()->count(3)->create(['owner_id' => $alice->id, 'converted_at' => now()]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                ['department' => 'sales', 'citation' => 'Alice led the team in lead conversions this quarter.'],
                ['department' => 'company', 'citation' => 'Alice was the top performer company-wide this quarter.'],
            ])]],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 30],
        ]),
    ]);

    $awards = app(QuarterlyAwardGenerator::class)->generate('2026-27', 2);

    expect($awards->firstWhere('department', UserRole::Sales->value)->citation)
        ->toBe('Alice led the team in lead conversions this quarter.');
});
