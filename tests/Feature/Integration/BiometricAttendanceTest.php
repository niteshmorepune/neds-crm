<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    config(['services.biometric.device_serial' => 'NFZ8243301103']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Auth / handshake
// ──────────────────────────────────────────────────────────────────────────────

it('responds to the ADMS device ping with the correct plain-text handshake', function () {
    $response = $this->get('/iclock/cdata?SN=NFZ8243301103&options=all&pushver=2.0.33S-20220613&language=English');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toContain('GET OPTION FROM:NFZ8243301103');
    expect($response->getContent())->toContain('ATTSTAMP=9999');
});

it('rejects a ping with an unknown serial number', function () {
    $this->get('/iclock/cdata?SN=UNKNOWN123&options=all')->assertStatus(403);
});

it('rejects a push with a missing SN parameter', function () {
    $this->call('POST', '/iclock/cdata', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '')
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Attendance record creation
// ──────────────────────────────────────────────────────────────────────────────

it('creates an attendance record with check-in from an ENTRY punch (status 0)', function () {
    $user = User::factory()->create(['device_user_id' => '3']);

    $body = "ATTLOG\n3\t2026-06-30 09:15:00\t0\t1\t0\t0\t0\n";

    $response = $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('OK: 1');

    $attendance = Attendance::where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->status)->toBe(AttendanceStatus::Present);

    $checkIn = Carbon::parse($attendance->check_in_at)->setTimezone('Asia/Kolkata');
    expect($checkIn->format('H:i'))->toBe('09:15');
    expect($attendance->check_out_at)->toBeNull();
});

it('creates an attendance record with check-out from an EXIT punch (status 1)', function () {
    $user = User::factory()->create(['device_user_id' => '14']);

    $body = "ATTLOG\n14\t2026-06-30 18:30:00\t1\t1\t0\t0\t0\n";

    $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $attendance = Attendance::where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull();
    expect($attendance->check_in_at)->toBeNull();

    $checkOut = Carbon::parse($attendance->check_out_at)->setTimezone('Asia/Kolkata');
    expect($checkOut->format('H:i'))->toBe('18:30');
});

it('sets both check-in and check-out when a full-day batch is pushed', function () {
    $user = User::factory()->create(['device_user_id' => '17']);

    $body = "ATTLOG\n17\t2026-06-30 09:00:00\t0\t1\t0\t0\t0\n17\t2026-06-30 18:00:00\t1\t1\t0\t0\t0\n";

    $response = $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    expect($response->getContent())->toContain('OK: 2');

    $attendance = Attendance::where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull();

    $checkIn = Carbon::parse($attendance->check_in_at)->setTimezone('Asia/Kolkata');
    $checkOut = Carbon::parse($attendance->check_out_at)->setTimezone('Asia/Kolkata');

    expect($checkIn->format('H:i'))->toBe('09:00');
    expect($checkOut->format('H:i'))->toBe('18:00');
});

it('keeps the earliest check-in when multiple entry punches arrive', function () {
    $user = User::factory()->create(['device_user_id' => '19']);

    // Two entry punches — first at 09:05, second at 09:00 (earlier, should win)
    $body = "ATTLOG\n19\t2026-06-30 09:05:00\t0\t1\t0\t0\t0\n19\t2026-06-30 09:00:00\t0\t1\t0\t0\t0\n";

    $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $attendance = Attendance::where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull();

    $checkIn = Carbon::parse($attendance->check_in_at)->setTimezone('Asia/Kolkata');
    expect($checkIn->format('H:i'))->toBe('09:00');
});

it('keeps the latest check-out when multiple exit punches arrive', function () {
    $user = User::factory()->create(['device_user_id' => '20']);

    // Two exit punches — first at 18:00, then later at 18:15
    $body = "ATTLOG\n20\t2026-06-30 18:00:00\t1\t1\t0\t0\t0\n20\t2026-06-30 18:15:00\t1\t1\t0\t0\t0\n";

    $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $attendance = Attendance::where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull();

    $checkOut = Carbon::parse($attendance->check_out_at)->setTimezone('Asia/Kolkata');
    expect($checkOut->format('H:i'))->toBe('18:15');
});

it('processes punches for multiple users in one batch', function () {
    $user1 = User::factory()->create(['device_user_id' => '21']);
    $user2 = User::factory()->create(['device_user_id' => '22']);

    $body = "ATTLOG\n21\t2026-06-30 09:10:00\t0\t1\t0\t0\t0\n22\t2026-06-30 09:20:00\t0\t1\t0\t0\t0\n";

    $response = $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    expect($response->getContent())->toContain('OK: 2');
    expect(Attendance::where('user_id', $user1->id)->exists())->toBeTrue();
    expect(Attendance::where('user_id', $user2->id)->exists())->toBeTrue();
});

it('skips and logs a warning for an unknown device_user_id', function () {
    $body = "ATTLOG\n99\t2026-06-30 09:00:00\t0\t1\t0\t0\t0\n";

    $response = $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('OK: 0');
    expect(Attendance::count())->toBe(0);
});

it('gracefully handles an empty or header-only body', function () {
    foreach (['', 'ATTLOG', "ATTLOG\n"] as $body) {
        $response = $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
            [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

        $response->assertStatus(200);
        expect($response->getContent())->toContain('OK: 0');
    }
});

it('stores timestamps in UTC and displays correctly in IST', function () {
    $user = User::factory()->create(['device_user_id' => '23']);

    // 09:00 IST = 03:30 UTC
    $body = "ATTLOG\n23\t2026-06-30 09:00:00\t0\t1\t0\t0\t0\n";

    $this->call('POST', '/iclock/cdata?SN=NFZ8243301103&table=ATTLOG&Stamp=9999',
        [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);

    $attendance = Attendance::where('user_id', $user->id)->first();
    expect($attendance)->not->toBeNull();

    $utc = Carbon::parse($attendance->check_in_at);
    expect($utc->format('H:i'))->toBe('03:30');
});
