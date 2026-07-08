<?php

use App\Enums\ProjectStatus;
use App\Jobs\DraftProjectDailyUpdate;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Command skips Sundays — travel forward if needed.
    if (now()->isSunday()) {
        $this->travelTo(now()->addDay());
    }
});

it('dispatches a job for each active project', function () {
    Bus::fake();
    $customer = Customer::factory()->create();
    Project::factory()->for($customer)->create(['status' => ProjectStatus::Active]);
    Project::factory()->for($customer)->create(['status' => ProjectStatus::Active]);

    $this->artisan('app:draft-project-daily-updates')->assertSuccessful();

    Bus::assertDispatchedTimes(DraftProjectDailyUpdate::class, 2);
});

it('skips on-hold and completed projects', function () {
    Bus::fake();
    $customer = Customer::factory()->create();
    Project::factory()->for($customer)->create(['status' => ProjectStatus::OnHold]);
    Project::factory()->for($customer)->create(['status' => ProjectStatus::Completed]);

    $this->artisan('app:draft-project-daily-updates')->assertSuccessful();

    Bus::assertNotDispatched(DraftProjectDailyUpdate::class);
});

it('skips a project already drafted for today', function () {
    Bus::fake();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['status' => ProjectStatus::Active]);
    Activity::create([
        'user_id' => null,
        'subject_type' => Project::class,
        'subject_id' => $project->id,
        'event' => DraftProjectDailyUpdate::ACTIVITY_EVENT,
        'changes' => ['date' => now()->toDateString()],
    ]);

    $this->artisan('app:draft-project-daily-updates')->assertSuccessful();

    Bus::assertNotDispatched(DraftProjectDailyUpdate::class);
});

it('does nothing on a Sunday', function () {
    Bus::fake();
    $this->travelTo(now()->next(Carbon\Carbon::SUNDAY));
    $customer = Customer::factory()->create();
    Project::factory()->for($customer)->create(['status' => ProjectStatus::Active]);

    $this->artisan('app:draft-project-daily-updates')->assertSuccessful();

    Bus::assertNotDispatched(DraftProjectDailyUpdate::class);
});
