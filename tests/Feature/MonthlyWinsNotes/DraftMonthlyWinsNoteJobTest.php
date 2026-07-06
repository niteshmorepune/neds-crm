<?php

use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Jobs\DraftMonthlyWinsNote;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MonthlyWinsNoteDrafted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function aiOnForWinsNote(): void
{
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
}

function fakeWinsNoteText(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $text]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 20],
        ]),
    ]);
}

it('drafts a monthly wins note and notifies the owner when there is something to report', function () {
    aiOnForWinsNote();
    fakeWinsNoteText('Great progress this month — 3 tasks wrapped up and your account is fully up to date!');
    Notification::fake();

    $owner = User::factory()->create();
    $customer = Customer::factory()->ownedBy($owner->id)->create();
    $project = Project::factory()->for($customer)->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => '2026-06-15'])->saveQuietly();

    DraftMonthlyWinsNote::dispatchSync($customer->id, '2026-06');

    $note = $customer->notes()->latest()->first();
    expect($note)->not->toBeNull();
    expect($note->user_id)->toBeNull();
    expect($note->body)->toContain('AI-drafted monthly update');
    expect($note->body)->toContain('Great progress this month');

    expect(Activity::where('subject_type', Customer::class)
        ->where('subject_id', $customer->id)
        ->where('event', 'monthly_wins_note_drafted')
        ->exists())->toBeTrue();

    Notification::assertSentTo($owner, MonthlyWinsNoteDrafted::class);
});

it('does not draft a note or call AI when the client has nothing to report that month', function () {
    aiOnForWinsNote();
    Http::fake();
    $customer = Customer::factory()->ownedBy(User::factory()->create()->id)->create();

    DraftMonthlyWinsNote::dispatchSync($customer->id, '2026-06');

    expect($customer->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('is idempotent — does not draft a second note for the same client and month', function () {
    aiOnForWinsNote();
    fakeWinsNoteText('Nice progress this month.');
    $customer = Customer::factory()->ownedBy(User::factory()->create()->id)->create();
    Activity::create([
        'user_id' => null,
        'subject_type' => Customer::class,
        'subject_id' => $customer->id,
        'event' => 'monthly_wins_note_drafted',
        'changes' => ['month' => '2026-06'],
    ]);

    DraftMonthlyWinsNote::dispatchSync($customer->id, '2026-06');

    expect(Note::where('notable_id', $customer->id)->where('notable_type', Customer::class)->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    Http::fake();
    $customer = Customer::factory()->ownedBy(User::factory()->create()->id)->create();
    $project = Project::factory()->for($customer)->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => '2026-06-15'])->saveQuietly();

    DraftMonthlyWinsNote::dispatchSync($customer->id, '2026-06');

    expect($customer->notes()->count())->toBe(0);
    Http::assertNothingSent();
});

it('only counts tasks, tickets and payments within the target month', function () {
    aiOnForWinsNote();
    fakeWinsNoteText('Solid month overall.');
    $customer = Customer::factory()->ownedBy(User::factory()->create()->id)->create();
    $project = Project::factory()->for($customer)->create();

    // Completed in May, not June — should not count toward the June report.
    Task::factory()->for($project)->create(['status' => TaskStatus::Done])
        ->forceFill(['completed_at' => '2026-05-20'])->saveQuietly();

    // A resolved ticket inside June — this alone should trigger the note.
    $customer->tickets()->create([
        'subject' => 'Billing question',
        'description' => 'Client asked about an invoice.',
        'status' => TicketStatus::Resolved,
        'sla_due_at' => now(),
        'resolved_at' => '2026-06-10',
    ]);

    DraftMonthlyWinsNote::dispatchSync($customer->id, '2026-06');

    expect($customer->notes()->count())->toBe(1);
});

it('counts payments received in the target month toward the wins note', function () {
    aiOnForWinsNote();
    fakeWinsNoteText('You cleared your balance in full this month.');
    $customer = Customer::factory()->ownedBy(User::factory()->create()->id)->create();
    $invoice = Invoice::factory()->for($customer)->create(['total' => 500000]);
    Payment::factory()->for($invoice)->create(['paid_on' => '2026-06-05', 'amount' => 500000]);

    DraftMonthlyWinsNote::dispatchSync($customer->id, '2026-06');

    expect($customer->notes()->count())->toBe(1);
});
