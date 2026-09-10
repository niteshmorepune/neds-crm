<?php

use App\Enums\LeadStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Jobs\SyncLeaveCoverToWadeskJob;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveCoverage;
use Carbon\Carbon;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);

    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────
// Approval requirement (LeaveCoverage::isRequiredFor / eligibleCovers)
// ──────────────────────────────────────────────────────────────────────────

it('requires covering_user_id to approve a Sales leave-taker with an open lead', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $rep = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($rep->id)->create(['status' => LeadStatus::New]);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id]);

    $this->actingAs($manager)
        ->post(route('leave-requests.approve', $leaveRequest))
        ->assertSessionHasErrors('covering_user_id');

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequestStatus::Pending);
});

it('approves without covering_user_id when the leave-taker has no open leads', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $rep = User::factory()->role(UserRole::Sales)->create();
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id]);

    $this->actingAs($manager)
        ->post(route('leave-requests.approve', $leaveRequest))
        ->assertRedirect();

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequestStatus::Approved)
        ->and($leaveRequest->fresh()->covering_user_id)->toBeNull();
});

it('approves without covering_user_id for a non-Sales/Telecaller leave-taker, even with a directly-set open lead', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['owner_id' => $support->id, 'status' => LeadStatus::New]);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $support->id]);

    $this->actingAs($manager)
        ->post(route('leave-requests.approve', $leaveRequest))
        ->assertRedirect();

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequestStatus::Approved);
});

it('rejects a covering_user_id for a different-role peer', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $rep = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($rep->id)->create(['status' => LeadStatus::New]);
    $wrongRole = User::factory()->role(UserRole::Support)->create();
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id]);

    $this->actingAs($manager)
        ->post(route('leave-requests.approve', $leaveRequest), ['covering_user_id' => $wrongRole->id])
        ->assertSessionHasErrors('covering_user_id');
});

it('rejects an inactive covering_user_id', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $rep = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($rep->id)->create(['status' => LeadStatus::New]);
    $inactivePeer = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id]);

    $this->actingAs($manager)
        ->post(route('leave-requests.approve', $leaveRequest), ['covering_user_id' => $inactivePeer->id])
        ->assertSessionHasErrors('covering_user_id');
});

it('accepts a valid same-role active peer as covering_user_id and dispatches sync jobs', function () {
    Queue::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $rep = User::factory()->role(UserRole::Sales)->create();
    $peer = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($rep->id)->create(['status' => LeadStatus::New]);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id]);

    $this->actingAs($manager)
        ->post(route('leave-requests.approve', $leaveRequest), ['covering_user_id' => $peer->id])
        ->assertRedirect();

    expect($leaveRequest->fresh()->status)->toBe(LeaveRequestStatus::Approved)
        ->and($leaveRequest->fresh()->covering_user_id)->toBe($peer->id);

    Queue::assertPushed(
        SyncLeaveCoverToWadeskJob::class,
        fn ($job) => $job->leadId === $lead->id && $job->leaveRequestId === $leaveRequest->id
    );
});

it('eligibleCovers matches a Telecaller peer held only as an additional role', function () {
    $rep = User::factory()->role(UserRole::Accounts)->withAdditionalRoles(UserRole::Telecaller)->create();
    $peer = User::factory()->role(UserRole::Intern)->withAdditionalRoles(UserRole::Telecaller)->create();
    $unrelated = User::factory()->role(UserRole::Sales)->create();

    $eligible = app(LeaveCoverage::class)->eligibleCovers($rep);

    expect($eligible->pluck('id'))->toContain($peer->id)
        ->and($eligible->pluck('id'))->not->toContain($unrelated->id)
        ->and($eligible->pluck('id'))->not->toContain($rep->id);
});

it('eligibleCovers falls back to a Sales peer when the leave-taker is the only active Telecaller', function () {
    // Real incident, 2026-09-10: exactly this shape in production — one
    // active Telecaller, no other Telecaller peer at all — left the
    // Approval Center's WhatsApp Chat Coverage dropdown permanently empty.
    $soleTelecaller = User::factory()->role(UserRole::Telecaller)->create();
    $salesPeer = User::factory()->role(UserRole::Sales)->create();
    $unrelated = User::factory()->role(UserRole::Support)->create();

    $eligible = app(LeaveCoverage::class)->eligibleCovers($soleTelecaller);

    expect($eligible->pluck('id'))->toContain($salesPeer->id)
        ->and($eligible->pluck('id'))->not->toContain($unrelated->id)
        ->and($eligible->pluck('id'))->not->toContain($soleTelecaller->id);
});

it('eligibleCovers never falls back to a Sales peer when a real Telecaller peer already exists', function () {
    $rep = User::factory()->role(UserRole::Telecaller)->create();
    $telecallerPeer = User::factory()->role(UserRole::Telecaller)->create();
    $salesPeer = User::factory()->role(UserRole::Sales)->create();

    $eligible = app(LeaveCoverage::class)->eligibleCovers($rep);

    expect($eligible->pluck('id'))->toContain($telecallerPeer->id)
        ->and($eligible->pluck('id'))->not->toContain($salesPeer->id);
});

// ──────────────────────────────────────────────────────────────────────────
// Scheduled command (app:sync-leave-cover-to-wadesk)
// ──────────────────────────────────────────────────────────────────────────

it('the sync command dispatches for a currently-active approved request with a covering user, not a pending/future/past/uncovered one', function () {
    Queue::fake();

    $rep = User::factory()->role(UserRole::Sales)->create();
    $peer = User::factory()->role(UserRole::Sales)->create();
    $activeLead = Lead::factory()->ownedBy($rep->id)->create(['status' => LeadStatus::New]);

    $active = LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'status' => LeaveRequestStatus::Approved,
        'covering_user_id' => $peer->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    // Pending — never dispatched regardless of dates.
    LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'status' => LeaveRequestStatus::Pending,
        'covering_user_id' => $peer->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    // Approved but in the future — not yet active.
    LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'status' => LeaveRequestStatus::Approved,
        'covering_user_id' => $peer->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
    ]);

    // Approved and currently in range, but no covering user set.
    LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'status' => LeaveRequestStatus::Approved,
        'covering_user_id' => null,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    $this->artisan('app:sync-leave-cover-to-wadesk')->assertSuccessful();

    Queue::assertPushed(SyncLeaveCoverToWadeskJob::class, 1);
    Queue::assertPushed(
        SyncLeaveCoverToWadeskJob::class,
        fn ($job) => $job->leadId === $activeLead->id && $job->leaveRequestId === $active->id
    );
});

// ──────────────────────────────────────────────────────────────────────────
// Job execution (SyncLeaveCoverToWadeskJob)
// ──────────────────────────────────────────────────────────────────────────

it('POSTs coverUntil as the end-of-day (IST) end_date while the leave request is currently active', function () {
    Http::fake(['https://wadesk.test/api/leads/set-cover' => Http::response(['status' => 'covered'], 200)]);

    $rep = User::factory()->create();
    $peer = User::factory()->create(['email' => 'neha@niranjanenterprises.co.in']);
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => '919028099919']);
    $leaveRequest = LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'status' => LeaveRequestStatus::Approved,
        'covering_user_id' => $peer->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle();

    $expectedUtc = Carbon::createFromFormat(
        'Y-m-d H:i:s',
        $leaveRequest->end_date->toDateString().' 23:59:59',
        'Asia/Kolkata'
    )->utc()->toIso8601String();

    Http::assertSent(function ($request) use ($expectedUtc) {
        return $request->url() === 'https://wadesk.test/api/leads/set-cover'
            && $request->header('X-Service-Key')[0] === 'wadesk-secret'
            && $request['phone'] === '919028099919'
            && $request['businessNumber'] === '919112095202'
            && $request['coveringAgentEmail'] === 'neha@niranjanenterprises.co.in'
            && $request['coverUntil'] === $expectedUtc;
    });
});

it('POSTs coverUntil as roughly now when the leave request is no longer currently active', function () {
    Http::fake(['https://wadesk.test/api/leads/set-cover' => Http::response(['status' => 'covered'], 200)]);

    $rep = User::factory()->create();
    $peer = User::factory()->create();
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => '919028099919']);
    $leaveRequest = LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'status' => LeaveRequestStatus::Approved,
        'covering_user_id' => $peer->id,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
    ]);

    (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle();

    Http::assertSent(function ($request) {
        $coverUntil = Carbon::parse($request['coverUntil']);

        return $coverUntil->diffInSeconds(now(), absolute: true) < 30;
    });
});

it('skips the HTTP call when the leave request has no covering_user_id', function () {
    Http::fake();

    $rep = User::factory()->create();
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => '919028099919']);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id, 'covering_user_id' => null]);

    (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the covering user is inactive', function () {
    Http::fake();

    $rep = User::factory()->create();
    $inactivePeer = User::factory()->create(['is_active' => false]);
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => '919028099919']);
    $leaveRequest = LeaveRequest::factory()->create([
        'user_id' => $rep->id,
        'covering_user_id' => $inactivePeer->id,
    ]);

    (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the lead has no phone', function () {
    Http::fake();

    $rep = User::factory()->create();
    $peer = User::factory()->create();
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => null]);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id, 'covering_user_id' => $peer->id]);

    (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the marketing number is not configured', function () {
    Http::fake();
    config(['services.wadesk.marketing_number' => null]);

    $rep = User::factory()->create();
    $peer = User::factory()->create();
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => '919028099919']);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id, 'covering_user_id' => $peer->id]);

    (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $rep = User::factory()->create();
    $peer = User::factory()->create();
    $lead = Lead::factory()->ownedBy($rep->id)->create(['phone' => '919028099919']);
    $leaveRequest = LeaveRequest::factory()->create(['user_id' => $rep->id, 'covering_user_id' => $peer->id]);

    expect(fn () => (new SyncLeaveCoverToWadeskJob($lead->id, $leaveRequest->id))->handle())
        ->not->toThrow(Throwable::class);
});
