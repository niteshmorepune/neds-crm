<?php

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Mail\DailyReportReminder;
use App\Models\CallLog;
use App\Models\DailyReport;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->user = User::factory()->role(UserRole::Sales)->create();
});

it('submits a daily report snapshotting the auto metrics', function () {
    Task::factory()->assignedTo($this->user->id)->status(TaskStatus::Done)->create();
    CallLog::factory()->count(2)->create(['user_id' => $this->user->id, 'called_at' => now()]);

    $this->actingAs($this->user)->post(route('daily-reports.store'), ['summary' => 'Closed two deals'])->assertRedirect();

    $report = DailyReport::where('user_id', $this->user->id)->firstOrFail();
    expect($report->tasks_completed)->toBe(1)
        ->and($report->calls_made)->toBe(2)
        ->and($report->summary)->toBe('Closed two deals')
        ->and($report->submitted_at)->not->toBeNull();
});

it('updates today\'s report instead of duplicating', function () {
    $this->actingAs($this->user)->post(route('daily-reports.store'), ['summary' => 'First']);
    $this->actingAs($this->user)->post(route('daily-reports.store'), ['summary' => 'Revised']);

    expect(DailyReport::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(DailyReport::where('user_id', $this->user->id)->first()->summary)->toBe('Revised');
});

it('requires a summary', function () {
    $this->actingAs($this->user)->post(route('daily-reports.store'), [])->assertSessionHasErrors('summary');
});

it('shows the team view to managers only', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    DailyReport::factory()->create(['user_id' => $this->user->id, 'summary' => 'My day went well']);

    $this->actingAs($manager)->get(route('daily-reports.team'))->assertOk()->assertSee('My day went well');
    $this->actingAs($this->user)->get(route('daily-reports.team'))->assertForbidden();
});

it('reminds only users who have not submitted today', function () {
    // Command skips Sundays — travel forward if needed.
    if (now()->isSunday()) {
        $this->travelTo(now()->addDay());
    }
    Mail::fake();
    $submitter = $this->user;
    DailyReport::factory()->create(['user_id' => $submitter->id]);
    $pending = User::factory()->role(UserRole::Support)->create();

    $this->artisan('app:send-daily-report-reminders')->assertSuccessful();

    Mail::assertSent(DailyReportReminder::class, fn (DailyReportReminder $m) => $m->hasTo($pending->email));
    Mail::assertNotSent(DailyReportReminder::class, fn (DailyReportReminder $m) => $m->hasTo($submitter->email));
});

it('renders the daily report page', function () {
    $this->actingAs($this->user)->get(route('daily-reports.index'))->assertOk()->assertSee('What I did today');
});
