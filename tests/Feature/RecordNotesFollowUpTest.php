<?php

use App\Livewire\RecordNotes;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * The 2026-09-17 "next_follow_up_at habit" fix — an optional Next follow-up
 * field on the Add Note form itself (RecordNotes), so a rep can set the
 * commitment date in the same action as noting it, instead of a separate
 * trip to the Lead/Deal Edit form. Same "only writes what's actually
 * filled in, never clears an existing value on a blank incidental field"
 * convention this codebase already established for stall_reason/goal.
 */
it('sets next_follow_up_at on a Lead when filled in alongside a note', function () {
    $lead = Lead::factory()->create(['next_follow_up_at' => null]);

    Livewire::test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'He said we will connect today at 5pm.')
        ->set('nextFollowUpAt', '2026-09-20T17:00')
        ->call('addNote');

    expect($lead->fresh()->next_follow_up_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'))
        ->toBe('2026-09-20 17:00');
});

it('sets next_follow_up_at on a Deal the same way', function () {
    $deal = Deal::factory()->create(['next_follow_up_at' => null]);

    Livewire::test(RecordNotes::class, ['record' => $deal, 'canManage' => true])
        ->set('body', 'Following up next week.')
        ->set('nextFollowUpAt', '2026-09-20T17:00')
        ->call('addNote');

    expect($deal->fresh()->next_follow_up_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'))
        ->toBe('2026-09-20 17:00');
});

it('leaving the follow-up field blank never clears an existing value', function () {
    $lead = Lead::factory()->create(['next_follow_up_at' => Carbon::createFromFormat('Y-m-d H:i', '2026-09-18 10:00', 'Asia/Kolkata')->utc()]);

    Livewire::test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Just a status update, no new commitment.')
        ->call('addNote');

    expect($lead->fresh()->next_follow_up_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'))
        ->toBe('2026-09-18 10:00');
});

it('is not offered to a user without canManage', function () {
    $lead = Lead::factory()->create();

    Livewire::test(RecordNotes::class, ['record' => $lead, 'canAddNotes' => true])
        ->assertDontSee('Next follow-up');

    expect(Livewire::test(RecordNotes::class, ['record' => $lead, 'canAddNotes' => true])->instance()->canSetFollowUp())
        ->toBeFalse();
});

it('rejects a malformed follow-up date without saving the note field', function () {
    $lead = Lead::factory()->create(['next_follow_up_at' => null]);

    Livewire::test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Note body.')
        ->set('nextFollowUpAt', 'not-a-date')
        ->call('addNote')
        ->assertHasErrors(['nextFollowUpAt']);

    expect($lead->fresh()->next_follow_up_at)->toBeNull();
});

it('resets the follow-up field after a successful submit', function () {
    $lead = Lead::factory()->create();

    Livewire::test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Note body.')
        ->set('nextFollowUpAt', '2026-09-20T17:00')
        ->call('addNote')
        ->assertSet('nextFollowUpAt', '');
});
