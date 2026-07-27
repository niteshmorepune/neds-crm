<?php

use App\Enums\UserRole;
use App\Mail\WeeklyOwnerDigest;
use App\Models\User;
use App\Models\WeeklyDigest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('caches an AI weekly digest on every admin/manager and emails each one', function () {
    Mail::fake();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Pipeline is healthy; two clients need attention this week.']],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 20],
        ]),
    ]);

    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);
    $manager = User::factory()->role(UserRole::Manager)->create(['is_active' => true]);
    $sales = User::factory()->role(UserRole::Sales)->create(['is_active' => true]);

    $this->artisan('app:send-weekly-owner-digest')->assertSuccessful();

    $admin->refresh();
    $manager->refresh();
    expect($admin->ai_weekly_digest)->toBe('Pipeline is healthy; two clients need attention this week.')
        ->and($admin->ai_weekly_digest_date->toDateString())->toBe(now()->toDateString())
        ->and($manager->ai_weekly_digest)->toBe('Pipeline is healthy; two clients need attention this week.');

    Mail::assertSent(WeeklyOwnerDigest::class, fn (WeeklyOwnerDigest $mail) => $mail->hasTo($admin->email));
    Mail::assertSent(WeeklyOwnerDigest::class, fn (WeeklyOwnerDigest $mail) => $mail->hasTo($manager->email));
    Mail::assertNotSent(WeeklyOwnerDigest::class, fn (WeeklyOwnerDigest $mail) => $mail->hasTo($sales->email));

    expect(WeeklyDigest::count())->toBe(1);
    $digest = WeeklyDigest::first();
    expect($digest->digest_date->toDateString())->toBe(now()->toDateString())
        ->and($digest->summary)->toBe('Pipeline is healthy; two clients need attention this week.');
});

it('still records a metrics-only snapshot, but sends nothing, when AI is disabled', function () {
    Mail::fake();
    config(['services.anthropic.enabled' => false]);
    Http::fake();

    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);

    $this->artisan('app:send-weekly-owner-digest')->assertSuccessful();

    Http::assertNothingSent();
    Mail::assertNothingSent();
    expect($admin->fresh()->ai_weekly_digest)->toBeNull();

    expect(WeeklyDigest::count())->toBe(1);
    $digest = WeeklyDigest::first();
    expect($digest->digest_date->toDateString())->toBe(now()->toDateString())
        ->and($digest->summary)->toBeNull();
});

it('is idempotent per day and never overwrites an already-recorded summary with a null one from a later AI-less run', function () {
    Mail::fake();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'First run summary.']],
            'usage' => ['input_tokens' => 40, 'output_tokens' => 20],
        ]),
    ]);
    User::factory()->role(UserRole::Admin)->create(['is_active' => true]);

    $this->artisan('app:send-weekly-owner-digest')->assertSuccessful();

    // A second run the same day, AI now off (e.g. quota exhausted mid-day).
    config(['services.anthropic.enabled' => false]);
    $this->artisan('app:send-weekly-owner-digest')->assertSuccessful();

    expect(WeeklyDigest::count())->toBe(1);
    expect(WeeklyDigest::first()->summary)->toBe('First run summary.');
});

/**
 * Same "Cannot redeclare" class of bug MorningDigestTest guards against
 * (2026-07-07, commit history) — Blade's compiled views are `require`d,
 * not `require_once`d, so a second render of the SAME view in one process
 * would crash if the template declared a plain PHP function inside @php.
 * This template doesn't, but render it for two recipients in one process
 * anyway to lock that in.
 */
it('renders the mail template for a second recipient in the same process without crashing', function () {
    $userA = User::factory()->role(UserRole::Admin)->create();
    $userB = User::factory()->role(UserRole::Manager)->create();

    $htmlA = (new WeeklyOwnerDigest($userA, Carbon::today(), 'Summary A.'))->render();
    $htmlB = (new WeeklyOwnerDigest($userB, Carbon::today(), 'Summary B.'))->render();

    expect($htmlA)->toBeString()->toContain('Summary A.')
        ->and($htmlB)->toBeString()->toContain('Summary B.');
});
