<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;
use App\Services\NextActionEngine;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function nextActionEngine(): NextActionEngine
{
    return app(NextActionEngine::class);
}

it('shows the attendance prompt before a role-specific prompt, even when both are pending', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('attendance_check_in');
    expect($action->subjectId)->toBe($sales->id);
    // the lead is still there, just not surfaced yet
    expect(Lead::find($lead->id)->status)->toBe(LeadStatus::New);
});

it('shows the meeting-starting-soon prompt before the Sales lead-call prompt, once checked in', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);
    $meeting = Meeting::factory()->create(['user_id' => $sales->id, 'meet_link' => 'https://meet.google.com/abc', 'occurred_at' => now()->addMinutes(5)]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('meeting_starting_soon');
    expect($action->subjectId)->toBe($meeting->id);
});

it('falls through to the next source once the earlier one has nothing pending', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $action = nextActionEngine()->nextFor($sales);

    expect($action->sourceKey)->toBe('sales_new_lead_call');
    expect($action->subjectId)->toBe($lead->id);
});

it('returns null once every source has nothing pending', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Attendance::factory()->for($sales)->create();

    expect(nextActionEngine()->nextFor($sales))->toBeNull();
});

it('completeFor() dispatches to the matching source by key', function () {
    $user = User::factory()->role(UserRole::Support)->create();

    nextActionEngine()->completeFor($user, 'attendance_check_in', $user->id);

    expect(nextActionEngine()->nextFor($user))->toBeNull();
});

it('completeFor() aborts on an unknown source key', function () {
    $user = User::factory()->role(UserRole::Support)->create();

    nextActionEngine()->completeFor($user, 'not_a_real_source', $user->id);
})->throws(NotFoundHttpException::class);
