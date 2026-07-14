<?php

use App\Enums\BiometricSyncStatus;
use App\Enums\UserRole;
use App\Models\BiometricSyncRequest;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    config(['services.biometric.bridge_token' => 'test-bridge-token']);
});

it('lets a manager request a biometric sync', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->post(route('attendance.biometric-sync'))->assertRedirect();

    $request = BiometricSyncRequest::firstOrFail();
    expect($request->status)->toBe(BiometricSyncStatus::Pending)
        ->and($request->requested_by_id)->toBe($manager->id);
});

it('forbids a non-manager from requesting a biometric sync', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($sales)->post(route('attendance.biometric-sync'))->assertForbidden();

    expect(BiometricSyncRequest::count())->toBe(0);
});

it('does not create a duplicate request while one is already pending', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->post(route('attendance.biometric-sync'));
    $this->actingAs($manager)->post(route('attendance.biometric-sync'));

    expect(BiometricSyncRequest::count())->toBe(1);
});

it('rejects the pending-check API without the correct bridge token', function () {
    $this->getJson('/api/biometric-sync/pending')->assertUnauthorized();
    $this->getJson('/api/biometric-sync/pending', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
});

it('reports no pending request when there is none', function () {
    $this->getJson('/api/biometric-sync/pending', ['Authorization' => 'Bearer test-bridge-token'])
        ->assertOk()
        ->assertJson(['pending' => false, 'id' => null]);
});

it('reports the oldest pending request', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $older = BiometricSyncRequest::create([
        'requested_by_id' => $manager->id, 'requested_at' => now()->subMinutes(5), 'status' => BiometricSyncStatus::Pending,
    ]);
    BiometricSyncRequest::create([
        'requested_by_id' => $manager->id, 'requested_at' => now(), 'status' => BiometricSyncStatus::Pending,
    ]);

    $this->getJson('/api/biometric-sync/pending', ['Authorization' => 'Bearer test-bridge-token'])
        ->assertOk()
        ->assertJson(['pending' => true, 'id' => $older->id]);
});

it('rejects the complete API without the correct bridge token', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $request = BiometricSyncRequest::create([
        'requested_by_id' => $manager->id, 'requested_at' => now(), 'status' => BiometricSyncStatus::Pending,
    ]);

    $this->postJson("/api/biometric-sync/{$request->id}/complete", ['status' => 'completed'])
        ->assertUnauthorized();

    expect($request->fresh()->status)->toBe(BiometricSyncStatus::Pending);
});

it('marks a request completed with a summary', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $request = BiometricSyncRequest::create([
        'requested_by_id' => $manager->id, 'requested_at' => now(), 'status' => BiometricSyncStatus::Pending,
    ]);

    $this->postJson("/api/biometric-sync/{$request->id}/complete", [
        'status' => 'completed',
        'summary' => 'Forwarded 2 user-day group(s) from 3 raw punch(es). CRM responded 200: OK: 3',
    ], ['Authorization' => 'Bearer test-bridge-token'])->assertOk();

    $request->refresh();
    expect($request->status)->toBe(BiometricSyncStatus::Completed)
        ->and($request->summary)->toContain('Forwarded 2 user-day group(s)')
        ->and($request->completed_at)->not->toBeNull();
});

it('marks a request failed with an error message', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $request = BiometricSyncRequest::create([
        'requested_by_id' => $manager->id, 'requested_at' => now(), 'status' => BiometricSyncStatus::Pending,
    ]);

    $this->postJson("/api/biometric-sync/{$request->id}/complete", [
        'status' => 'failed',
        'error' => "Cannot read properties of null (reading 'subarray')",
    ], ['Authorization' => 'Bearer test-bridge-token'])->assertOk();

    $request->refresh();
    expect($request->status)->toBe(BiometricSyncStatus::Failed)
        ->and($request->error)->toContain('subarray');
});

it('shows the latest sync status to a manager on the attendance page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    BiometricSyncRequest::create([
        'requested_by_id' => $manager->id, 'requested_at' => now(), 'status' => BiometricSyncStatus::Pending,
    ]);

    $this->actingAs($manager)->get(route('attendance.index'))
        ->assertOk()
        ->assertSee('Biometric sync requested');
});

it('does not show the sync status or button to a non-manager', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    BiometricSyncRequest::create([
        'requested_by_id' => $sales->id, 'requested_at' => now(), 'status' => BiometricSyncStatus::Pending,
    ]);

    $this->actingAs($sales)->get(route('attendance.index'))
        ->assertOk()
        ->assertDontSee('Biometric sync requested')
        ->assertDontSee('Sync from biometric');
});
