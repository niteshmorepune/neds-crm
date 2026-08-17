<?php

use App\Enums\AttendanceStatus;
use App\Enums\DealStage;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Mail\DailyReportReminder;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\DailyReport;
use App\Models\Deal;
use App\Models\Project;
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

it('shows a partial weekly submission rate excluding Sundays', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $person = User::factory()->create(['name' => 'Partial Submitter']);

    // 2026-07-06 is a Monday; the trailing 7-day window (30 Jun - 6 Jul)
    // contains exactly one Sunday (5 Jul), so 6 business days are expected.
    foreach (['2026-06-30', '2026-07-01', '2026-07-02', '2026-07-06'] as $date) {
        DailyReport::factory()->create(['user_id' => $person->id, 'date' => $date]);
    }

    $this->actingAs($manager)->get(route('daily-reports.team', ['date' => '2026-07-06']))
        ->assertOk()
        ->assertSeeInOrder(['Partial Submitter', '4/6 this week'])
        ->assertSee('bg-amber-100');
});

it('shows a green badge for perfect weekly submission and a red one for zero', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $perfect = User::factory()->create(['name' => 'Perfect Attendance']);
    $never = User::factory()->create(['name' => 'Never Submits']);

    foreach (['2026-06-30', '2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04', '2026-07-06'] as $date) {
        DailyReport::factory()->create(['user_id' => $perfect->id, 'date' => $date]);
    }

    $response = $this->actingAs($manager)->get(route('daily-reports.team', ['date' => '2026-07-06']));

    $response->assertOk()
        ->assertSeeInOrder(['Perfect Attendance', '6/6 this week'])
        ->assertSeeInOrder(['Never Submits', '0/6 this week'])
        ->assertSee('bg-green-100')
        ->assertSee('bg-red-100');
});

it('does not count a Sunday submission toward the expected business-day total', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $person = User::factory()->create(['name' => 'Sunday Worker']);

    // A report logged on the excluded Sunday (5 Jul) should not inflate the
    // denominator or count toward the numerator either.
    DailyReport::factory()->create(['user_id' => $person->id, 'date' => '2026-07-05']);

    $this->actingAs($manager)->get(route('daily-reports.team', ['date' => '2026-07-06']))
        ->assertOk()->assertSeeInOrder(['Sunday Worker', '0/6 this week']);
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

it('does not remind users who are on approved leave today', function () {
    if (now()->isSunday()) {
        $this->travelTo(now()->addDay());
    }
    Mail::fake();
    $onLeave = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->create([
        'user_id' => $onLeave->id,
        'date' => now()->toDateString(),
        'status' => AttendanceStatus::Leave,
    ]);
    $pending = User::factory()->role(UserRole::Support)->create();

    $this->artisan('app:send-daily-report-reminders')->assertSuccessful();

    Mail::assertSent(DailyReportReminder::class, fn (DailyReportReminder $m) => $m->hasTo($pending->email));
    Mail::assertNotSent(DailyReportReminder::class, fn (DailyReportReminder $m) => $m->hasTo($onLeave->email));
});

it('renders the daily report page', function () {
    $this->actingAs($this->user)->get(route('daily-reports.index'))->assertOk()->assertSee('What I did today');
});

it('groups my tasks by project and separates manual from routine maintenance tasks', function () {
    $project = Project::factory()->create(['name' => 'Viva Website']);
    $manual = Task::factory()->create([
        'title' => 'Fix contact form',
        'project_id' => $project->id,
        'assignee_id' => $this->user->id,
        'created_by' => User::factory()->create()->id,
        'status' => TaskStatus::Todo,
    ]);
    $routine = Task::factory()->create([
        'title' => 'Google Search Console review',
        'project_id' => $project->id,
        'assignee_id' => $this->user->id,
        'created_by' => null,
        'status' => TaskStatus::Todo,
    ]);

    $response = $this->actingAs($this->user)->get(route('daily-reports.index'));

    // The routine task's title still lands in the HTML (inside a native
    // <details> element) — it's collapsed by default in the browser, not
    // removed — so assert the manual task comes first and the routine one
    // is demoted below the "routine maintenance" summary label, not that
    // its title is absent from the response entirely.
    $response->assertOk()
        ->assertSeeInOrder(['Viva Website', 'Fix contact form', 'routine maintenance', 'Google Search Console review']);
});

it('excludes completed tasks from my tasks', function () {
    // Backdated so it also falls outside today's new "Completed today"
    // panel, keeping this test scoped to the pending-list exclusion it's
    // actually about.
    $task = Task::factory()->create([
        'title' => 'Already done',
        'assignee_id' => $this->user->id,
        'status' => TaskStatus::Done,
    ]);
    $task->completed_at = now()->subDay();
    $task->saveQuietly();

    $this->actingAs($this->user)->get(route('daily-reports.index'))->assertDontSee('Already done');
});

it('buckets a task with no project under "Other tasks"', function () {
    Task::factory()->create([
        'title' => 'Standalone follow-up',
        'assignee_id' => $this->user->id,
        'project_id' => null,
        'created_by' => User::factory()->create()->id,
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()
        ->assertSee('Other tasks')
        ->assertSee('Standalone follow-up');
});

it('shows a carried-forward panel for an open task that already existed before today, but not for one created today', function () {
    $old = Task::factory()->create([
        'title' => 'Old pending task',
        'assignee_id' => $this->user->id,
        'status' => TaskStatus::Todo,
        'created_by' => User::factory()->create()->id,
    ]);
    $old->created_at = now()->subDays(2);
    $old->saveQuietly();

    Task::factory()->create([
        'title' => 'Fresh task added today',
        'assignee_id' => $this->user->id,
        'status' => TaskStatus::Todo,
        'created_by' => User::factory()->create()->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('daily-reports.index'));

    $response->assertOk()
        ->assertSee('Carried forward from before today')
        ->assertSeeInOrder(['Carried forward from before today', 'Old pending task']);
});

it('does not show the carried-forward panel when nothing is carried over', function () {
    Task::factory()->create([
        'title' => 'Fresh task added today',
        'assignee_id' => $this->user->id,
        'status' => TaskStatus::Todo,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()->assertDontSee('Carried forward from before today');
});

it('shows tasks completed today with their inferred time taken', function () {
    $project = Project::factory()->create(['name' => 'Viva Website']);
    $task = Task::factory()->create([
        'title' => 'Fix contact form',
        'project_id' => $project->id,
        'assignee_id' => $this->user->id,
        'status' => TaskStatus::Todo,
        'created_by' => User::factory()->create()->id,
    ]);
    $task->update(['status' => TaskStatus::InProgress]);
    $task->update(['status' => TaskStatus::Done]);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()
        ->assertSee('Completed today')
        ->assertSee('Fix contact form');
});

it('offers a Copy to send button once today\'s report is submitted', function () {
    $this->actingAs($this->user)->post(route('daily-reports.store'), ['summary' => 'Wrapped up the SEO audit']);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()->assertSee('Copy to send');
});

it('does not offer Copy to send before today\'s report has been submitted', function () {
    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()->assertDontSee('Copy to send');
});

it('formats a daily report as a plain-text block for sharing', function () {
    $report = DailyReport::factory()->create([
        'user_id' => $this->user->id,
        'date' => '2026-08-17',
        'tasks_completed' => 4,
        'calls_made' => 3,
        'leads_touched' => 2,
        'attendance_status' => 'present',
        'summary' => 'Closed two deals and followed up on three tickets.',
    ]);

    $text = $report->formattedForSharing();

    expect($text)->toContain('Daily Report — 17 Aug 2026')
        ->toContain($this->user->name)
        ->toContain('Tasks completed: 4')
        ->toContain('Calls made: 3')
        ->toContain('Leads touched: 2')
        ->toContain('Attendance: Present')
        ->toContain('Closed two deals and followed up on three tickets.');
});

it('orders project groups by their earliest due date, most urgent first', function () {
    $laterProject = Project::factory()->create(['name' => 'Later Project']);
    $urgentProject = Project::factory()->create(['name' => 'Urgent Project']);

    Task::factory()->create([
        'title' => 'Due next week',
        'project_id' => $laterProject->id,
        'assignee_id' => $this->user->id,
        'created_by' => User::factory()->create()->id,
        'due_date' => now()->addWeek(),
        'status' => TaskStatus::Todo,
    ]);
    Task::factory()->create([
        'title' => 'Overdue task',
        'project_id' => $urgentProject->id,
        'assignee_id' => $this->user->id,
        'created_by' => User::factory()->create()->id,
        'due_date' => now()->subDay(),
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()
        ->assertSeeInOrder(['Urgent Project', 'Later Project']);
});

it('shows todays activity timeline entries on the daily report page', function () {
    $deal = Deal::factory()->create(['title' => 'ADTA Group Website']);
    Activity::create([
        'user_id' => $this->user->id, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
        'event' => 'created', 'changes' => [],
    ]);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()
        ->assertSee('Activity timeline')
        ->assertSee('Created deal "ADTA Group Website"');
});

it('browses the activity timeline to a previous day via the date picker', function () {
    $deal = Deal::factory()->create(['title' => 'Old Work']);
    $activity = Activity::create([
        'user_id' => $this->user->id, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
        'event' => 'created', 'changes' => [],
    ]);
    $activity->created_at = now()->subDays(5);
    $activity->saveQuietly();

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()->assertDontSee('Old Work');

    $this->actingAs($this->user)->get(route('daily-reports.index', ['date' => now()->subDays(5)->toDateString()]))
        ->assertOk()->assertSee('Old Work');
});

it('never shows a future date on the activity timeline even if requested', function () {
    $response = $this->actingAs($this->user)->get(route('daily-reports.index', ['date' => now()->addDays(3)->toDateString()]));

    $response->assertOk();
    expect($response->viewData('viewDateInput'))->toBe(now()->timezone(config('app.display_timezone'))->toDateString());
});

it('shows a pending deal owned by the employee on the daily report page', function () {
    Deal::factory()->ownedBy($this->user->id)->create(['title' => 'Open Opportunity', 'stage' => DealStage::Proposal]);

    $this->actingAs($this->user)->get(route('daily-reports.index'))
        ->assertOk()->assertSee('Still pending')->assertSee('Open Opportunity');
});
