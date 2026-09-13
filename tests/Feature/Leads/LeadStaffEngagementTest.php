<?php

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Regression coverage for Lead::hasStaffEngagementSince() — added
 * 2026-09-13 after a real incident (lead #322): staff had been actively
 * working a lead entirely by phone, but every automated-funnel guard in
 * this app only ever checked Lead::hasStaffWhatsappReplySince(), so an
 * automated recovery nudge fired an unrelated ₹299 offer on top of a live
 * human conversation. See CLAUDE.md's decisions log for the full incident.
 */
it('counts a real staff phone call as engagement, regardless of outcome', function () {
    $lead = Lead::factory()->create();
    $since = now()->subDay();

    CallLog::factory()->create([
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'called_at' => now(),
    ]);

    expect($lead->hasStaffEngagementSince($since))->toBeTrue();
});

it('counts a plain staff-authored note as engagement', function () {
    $lead = Lead::factory()->create();
    $since = now()->subDay();

    $lead->notes()->create([
        'user_id' => User::factory()->create()->id,
        'body' => 'I informed the client that the proposal is ready and scheduled a call at 2 PM.',
    ]);

    expect($lead->hasStaffEngagementSince($since))->toBeTrue();
});

it('still counts a staff WhatsApp reply as engagement', function () {
    $lead = Lead::factory()->create();
    $since = now()->subDay();

    $lead->notes()->create(['body' => "[Sent via WhatsApp by Mohit Patil]\nOkay sir"]);

    expect($lead->hasStaffEngagementSince($since))->toBeTrue();
});

it('does not count a system/automated note with no staff author', function () {
    $lead = Lead::factory()->create();
    $since = now()->subDay();

    $lead->notes()->create([
        'user_id' => null,
        'body' => '✨ Automated recovery nudge sent via WhatsApp — still hasn\'t completed the offer.',
    ]);

    expect($lead->hasStaffEngagementSince($since))->toBeFalse();
});

it('does not count a call or note that happened before the cutoff', function () {
    $lead = Lead::factory()->create();
    $since = now();

    CallLog::factory()->create([
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'called_at' => now()->subDays(2),
    ]);
    $note = $lead->notes()->create([
        'user_id' => User::factory()->create()->id,
        'body' => 'Old note before the cutoff.',
    ]);
    $note->forceFill(['created_at' => now()->subDays(2)])->save();

    expect($lead->hasStaffEngagementSince($since))->toBeFalse();
});
