<?php

use App\Models\Deal;

it('is close to created_at for a deal with no notes and no edits since creation', function () {
    $deal = Deal::factory()->create();

    expect($deal->lastTouchedAt()->diffInSeconds($deal->created_at))->toBeLessThan(2);
});

it('picks the most recent note over an older logged edit', function () {
    $deal = Deal::factory()->create();
    $deal->update(['value' => $deal->value + 1]); // logs an "updated" activity
    $note = $deal->notes()->create(['user_id' => null, 'body' => 'A note, added after the edit above.']);
    $note->forceFill(['created_at' => now()->addMinute()])->save();

    expect($deal->lastTouchedAt()->eq($note->created_at))->toBeTrue();
});

it('picks a more recent logged edit over an older note', function () {
    $deal = Deal::factory()->create();
    $note = $deal->notes()->create(['user_id' => null, 'body' => 'An older note.']);
    $note->forceFill(['created_at' => now()->subDay()])->save();

    $deal->update(['value' => $deal->value + 1]); // logs an "updated" activity just now
    $lastActivity = $deal->activities()->latest('created_at')->first();

    expect($deal->lastTouchedAt()->eq($lastActivity->created_at))->toBeTrue();
});
