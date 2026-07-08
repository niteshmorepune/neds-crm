<?php

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Mail\ProjectUpdatesDigest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Command skips Sundays — travel forward if needed.
    if (now()->isSunday()) {
        $this->travelTo(now()->addDay());
    }
});

function projectWithDraft(int $daysOld, bool $approved = false): Project
{
    $owner = User::factory()->create();
    $customer = Customer::factory()->create();
    $project = Project::factory()->for($customer)->create(['owner_id' => $owner->id, 'status' => ProjectStatus::Active]);

    $note = $project->notes()->create([
        'user_id' => null,
        'body' => 'AI draft body.',
        'visible_to_client' => $approved,
        'ai_generated' => true,
    ]);
    $note->forceFill(['created_at' => now()->subDays($daysOld)])->saveQuietly();

    return $project;
}

it('emails every active admin and manager when there is something to report', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);
    $manager = User::factory()->role(UserRole::Manager)->create(['is_active' => true]);
    $inactiveAdmin = User::factory()->role(UserRole::Admin)->create(['is_active' => false]);
    $sales = User::factory()->role(UserRole::Sales)->create(['is_active' => true]);
    projectWithDraft(daysOld: 3);

    $this->artisan('app:send-project-updates-digest')->assertSuccessful();

    Mail::assertSent(ProjectUpdatesDigest::class, 2);
    Mail::assertSent(ProjectUpdatesDigest::class, fn ($mail) => $mail->hasTo($admin->email));
    Mail::assertSent(ProjectUpdatesDigest::class, fn ($mail) => $mail->hasTo($manager->email));
    Mail::assertNotSent(ProjectUpdatesDigest::class, fn ($mail) => $mail->hasTo($inactiveAdmin->email));
    Mail::assertNotSent(ProjectUpdatesDigest::class, fn ($mail) => $mail->hasTo($sales->email));
});

it('sends nothing when there is nothing to report', function () {
    Mail::fake();
    User::factory()->role(UserRole::Admin)->create(['is_active' => true]);
    Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->artisan('app:send-project-updates-digest')->assertSuccessful();

    Mail::assertNothingSent();
});

it('does nothing on a Sunday', function () {
    Mail::fake();
    $this->travelTo(now()->next(Carbon\Carbon::SUNDAY));
    User::factory()->role(UserRole::Admin)->create(['is_active' => true]);
    projectWithDraft(daysOld: 10);

    $this->artisan('app:send-project-updates-digest')->assertSuccessful();

    Mail::assertNothingSent();
});

it('buckets yesterday\'s drafts into approved vs still pending', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);

    $approvedYesterday = projectWithDraft(daysOld: 1, approved: true);
    $pendingYesterday = projectWithDraft(daysOld: 1, approved: false);
    // Drafted today — should not count as "yesterday's".
    projectWithDraft(daysOld: 0, approved: false);

    $this->artisan('app:send-project-updates-digest')->assertSuccessful();

    Mail::assertSent(ProjectUpdatesDigest::class, function (ProjectUpdatesDigest $mail) use ($admin) {
        return $mail->hasTo($admin->email)
            && $mail->yesterdaysDrafts->count() === 2
            && $mail->yesterdaysDrafts->where('visible_to_client', true)->count() === 1;
    });
});

it('flags drafts unapproved past the stale-days threshold but not fresher ones', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);

    $stale = projectWithDraft(daysOld: 5);
    $fresh = projectWithDraft(daysOld: 1);
    $approvedOld = projectWithDraft(daysOld: 5, approved: true);

    $this->artisan('app:send-project-updates-digest', ['--stale-days' => 2])->assertSuccessful();

    Mail::assertSent(ProjectUpdatesDigest::class, function (ProjectUpdatesDigest $mail) use ($admin, $stale) {
        return $mail->hasTo($admin->email)
            && $mail->staleDrafts->count() === 1
            && $mail->staleDrafts->first()->notable->is($stale);
    });
});

it('flags an active project with no completed task or note in quiet-days but not a recently active one', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);

    $quiet = Project::factory()->create(['status' => ProjectStatus::Active]);
    $quiet->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    $active = Project::factory()->create(['status' => ProjectStatus::Active]);
    $active->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
    Task::factory()->for($active)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => now()->subDay()])->saveQuietly();

    $this->artisan('app:send-project-updates-digest', ['--quiet-days' => 5])->assertSuccessful();

    Mail::assertSent(ProjectUpdatesDigest::class, function (ProjectUpdatesDigest $mail) use ($admin, $quiet, $active) {
        $flaggedIds = $mail->quietProjects->pluck('project.id');

        return $mail->hasTo($admin->email)
            && $flaggedIds->contains($quiet->id)
            && ! $flaggedIds->contains($active->id);
    });
});

it('does not flag a brand-new active project as quiet before it has had a chance to be touched', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);
    $brandNew = Project::factory()->create(['status' => ProjectStatus::Active]);
    // Something else needs to be flagged, otherwise the whole email is skipped.
    projectWithDraft(daysOld: 10);

    $this->artisan('app:send-project-updates-digest', ['--quiet-days' => 5])->assertSuccessful();

    Mail::assertSent(ProjectUpdatesDigest::class, function (ProjectUpdatesDigest $mail) use ($admin, $brandNew) {
        return $mail->hasTo($admin->email)
            && ! $mail->quietProjects->pluck('project.id')->contains($brandNew->id);
    });
});

it('does not flag on-hold or completed projects as quiet', function () {
    Mail::fake();
    $admin = User::factory()->role(UserRole::Admin)->create(['is_active' => true]);

    $onHold = Project::factory()->create(['status' => ProjectStatus::OnHold]);
    $onHold->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
    $completed = Project::factory()->create(['status' => ProjectStatus::Completed]);
    $completed->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
    // Something else needs to be flagged, otherwise the whole email is skipped.
    projectWithDraft(daysOld: 10);

    $this->artisan('app:send-project-updates-digest', ['--quiet-days' => 5])->assertSuccessful();

    Mail::assertSent(ProjectUpdatesDigest::class, function (ProjectUpdatesDigest $mail) use ($admin, $onHold, $completed) {
        $flaggedIds = $mail->quietProjects->pluck('project.id');

        return $mail->hasTo($admin->email)
            && ! $flaggedIds->contains($onHold->id)
            && ! $flaggedIds->contains($completed->id);
    });
});
