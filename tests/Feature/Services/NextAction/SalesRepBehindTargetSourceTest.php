<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\NextActionSnooze;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\NextAction\SalesRepBehindTargetSource;
use Illuminate\Support\Carbon;

function salesRepBehindTargetSource(): SalesRepBehindTargetSource
{
    return app(SalesRepBehindTargetSource::class);
}

// The 20th of a real month — safely past MIN_DAY_OF_MONTH (7), with the
// month roughly two-thirds elapsed (~65-67%). .utc() avoids the Carbon
// testNow-timezone-leak gotcha (see [[feedback-gotchas]]).
const SALES_BEHIND_TEST_MID_MONTH = '2026-09-20 11:00';

const SALES_BEHIND_TEST_EARLY_MONTH = '2026-09-03 11:00';

function targetedRep(int $targetRupees, int $wonThisMonthRupees): User
{
    $rep = User::factory()->role(UserRole::Sales)->create();

    SalesTarget::factory()->create([
        'user_id' => $rep->id,
        'target_value' => $targetRupees * 100,
    ]);

    if ($wonThisMonthRupees > 0) {
        Deal::factory()->create([
            'owner_id' => $rep->id,
            'stage' => DealStage::Won,
            'value' => $wonThisMonthRupees * 100,
            'won_at' => now(),
        ]);
    }

    return $rep;
}

it('returns null for a non-Admin/Manager user', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $sales = User::factory()->role(UserRole::Sales)->create();
    targetedRep(targetRupees: 100000, wonThisMonthRupees: 5000);

    expect(salesRepBehindTargetSource()->next($sales))->toBeNull();

    Carbon::setTestNow();
});

it('prompts an Admin to check in with a rep well behind their monthly sales target', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    $rep = targetedRep(targetRupees: 100000, wonThisMonthRupees: 5000); // 5% actual vs ~65% of month elapsed

    $action = salesRepBehindTargetSource()->next($admin);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($rep->id);
    expect($action->title)->toBe("Check in with {$rep->name}");
    expect($action->actionUrl)->toBe(route('employees.show', $rep));

    Carbon::setTestNow();
});

it('does not flag a rep comfortably on pace', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    targetedRep(targetRupees: 100000, wonThisMonthRupees: 70000); // 70% actual vs ~65% of month elapsed

    expect(salesRepBehindTargetSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('does not flag a rep with no sales target set at all', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    User::factory()->role(UserRole::Sales)->create(); // no SalesTarget row

    expect(salesRepBehindTargetSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('does not flag anyone before the first week of the month', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_EARLY_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    targetedRep(targetRupees: 100000, wonThisMonthRupees: 0);

    expect(salesRepBehindTargetSource()->next($admin))->toBeNull();

    Carbon::setTestNow();
});

it('excludes a snoozed rep but includes them again once the snooze expires', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    $rep = targetedRep(targetRupees: 100000, wonThisMonthRupees: 5000);

    NextActionSnooze::create([
        'user_id' => $admin->id,
        'source_key' => 'sales_rep_behind_target',
        'subject_type' => User::class,
        'subject_id' => $rep->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(salesRepBehindTargetSource()->next($admin))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(salesRepBehindTargetSource()->next($admin)?->subjectId)->toBe($rep->id);

    Carbon::setTestNow();
});

it('picks the worst-behind rep when several are behind at once', function () {
    Carbon::setTestNow(Carbon::parse(SALES_BEHIND_TEST_MID_MONTH, config('app.display_timezone'))->utc());
    $admin = User::factory()->role(UserRole::Admin)->create();
    targetedRep(targetRupees: 100000, wonThisMonthRupees: 30000); // 30% actual, behind but less so
    $worst = targetedRep(targetRupees: 100000, wonThisMonthRupees: 2000); // 2% actual, furthest behind

    $action = salesRepBehindTargetSource()->next($admin);

    expect($action?->subjectId)->toBe($worst->id);

    Carbon::setTestNow();
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    salesRepBehindTargetSource()->complete($admin, $admin->id);
})->throws(RuntimeException::class);
