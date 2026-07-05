<?php

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Mail\MorningDigest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Command skips Sundays — travel forward if needed.
    if (now()->isSunday()) {
        $this->travelTo(now()->addDay());
    }
});

it('caches an AI daily-priorities summary on the user and includes it in the email', function () {
    Mail::fake();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'You have 1 overdue task — tackle that first today.']],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15],
        ]),
    ]);

    $user = User::factory()->role(UserRole::Sales)->create(['is_active' => true]);
    Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Todo,
        'due_date' => now()->subDay(),
    ]);

    $this->artisan('app:send-morning-digest')->assertSuccessful();

    $user->refresh();
    expect($user->ai_daily_digest)->toBe('You have 1 overdue task — tackle that first today.')
        ->and($user->ai_daily_digest_date->toDateString())->toBe(now()->toDateString());

    Mail::assertSent(MorningDigest::class, fn (MorningDigest $mail) => $mail->hasTo($user->email)
        && $mail->aiSummary === 'You have 1 overdue task — tackle that first today.');
});

it('sends the digest without an AI summary when AI is disabled', function () {
    Mail::fake();
    config(['services.anthropic.enabled' => false]);
    Http::fake();

    $user = User::factory()->role(UserRole::Sales)->create(['is_active' => true]);
    Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Todo,
        'due_date' => now()->subDay(),
    ]);

    $this->artisan('app:send-morning-digest')->assertSuccessful();

    $user->refresh();
    expect($user->ai_daily_digest)->toBeNull()
        ->and($user->ai_daily_digest_date)->toBeNull();

    Http::assertNothingSent();
    Mail::assertSent(MorningDigest::class, fn (MorningDigest $mail) => $mail->hasTo($user->email) && $mail->aiSummary === null);
});

it('does not call AI when the user has nothing due', function () {
    Mail::fake();
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake();

    User::factory()->role(UserRole::Sales)->create(['is_active' => true]);

    $this->artisan('app:send-morning-digest')->assertSuccessful();

    Http::assertNothingSent();
    Mail::assertNothingSent();
});
