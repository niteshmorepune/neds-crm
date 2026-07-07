<?php

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Mail\MorningDigest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Regression coverage for the "Cannot redeclare digestTable()" production bug
 * (2026-07-07): the mail template used to declare plain PHP functions inside
 * a @php block. Blade's compiled views are `require`d, not `require_once`d,
 * so rendering the SAME view for a second recipient in one process crashed.
 * Mail::fake() would NOT have caught this — it skips rendering entirely — so
 * these tests deliberately render the mailable for real (MAIL_MAILER=array
 * in phpunit.xml still compiles the Blade view, it just doesn't hit SMTP).
 */
function digestFor(User $user): MorningDigest
{
    return new MorningDigest(
        user: $user,
        date: Carbon::today(),
        overdueTasks: Task::where('assignee_id', $user->id)->get(),
        dueTodayTasks: new Collection,
        callFollowUps: new Collection,
        leadFollowUps: new Collection,
        dealFollowUps: new Collection,
        openTickets: new Collection,
    );
}

it('renders the digest for a second recipient in the same process without crashing', function () {
    $userA = User::factory()->role(UserRole::Sales)->create();
    $userB = User::factory()->role(UserRole::Support)->create();
    Task::factory()->create(['assignee_id' => $userA->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);
    Task::factory()->create(['assignee_id' => $userB->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);

    $htmlA = digestFor($userA)->render();
    $htmlB = digestFor($userB)->render();

    expect($htmlA)->toBeString()->and($htmlB)->toBeString();
});

it('sends the command-level digest to multiple active users without throwing', function () {
    $userA = User::factory()->role(UserRole::Sales)->create();
    $userB = User::factory()->role(UserRole::Support)->create();
    Task::factory()->create(['assignee_id' => $userA->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);
    Task::factory()->create(['assignee_id' => $userB->id, 'status' => TaskStatus::Todo, 'due_date' => now()->subDay()]);

    $this->artisan('app:send-morning-digest')->assertSuccessful();
});

it('skips users with nothing due, sending no mail for them', function () {
    $userWithNothing = User::factory()->role(UserRole::Sales)->create();

    $this->artisan('app:send-morning-digest')->assertSuccessful();

    expect($userWithNothing->fresh()->ai_daily_digest)->toBeNull();
});
