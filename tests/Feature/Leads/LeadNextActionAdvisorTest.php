<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\StallReason;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\User;
use App\Services\LeadNextActionAdvisor;
use Illuminate\Support\Carbon;

function nextActionAdvisor(): LeadNextActionAdvisor
{
    return app(LeadNextActionAdvisor::class);
}

/**
 * lastTouchedAt() floors at both created_at AND the subject's own 'created'
 * Activity row, which always logs at the real wall-clock moment regardless
 * of a backdated created_at passed to the model — same gotcha/helper as
 * ObjectionFollowUpDueSourceTest::backdateCreation().
 */
function backdateLeadCreation(Lead $lead, Carbon $when): void
{
    Activity::where('subject_type', Lead::class)->where('subject_id', $lead->id)->where('event', 'created')->update(['created_at' => $when]);
}

/** A cold-call lead never triggers LeadCallTimingAdvisor's capture-hour signal, isolating tests that don't care about it. */
function coldCallLeadForNextAction(array $attributes = []): Lead
{
    return Lead::factory()->create(['source' => LeadSource::ColdCall, ...$attributes]);
}

beforeEach(function () {
    $this->advisor = nextActionAdvisor();
});

it('surfaces a stalling tag when no contact in 3+ days', function () {
    $createdAt = now()->subDays(5);
    $lead = coldCallLeadForNextAction(['status' => LeadStatus::Contacted, 'stall_reason' => StallReason::Budget, 'created_at' => $createdAt]);
    backdateLeadCreation($lead, $createdAt);
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::Connected,
        'called_at' => now()->subDays(4),
    ]);

    $hint = $this->advisor->hintFor($lead);

    expect($hint['label'])->toContain('Stalling')->toContain('Budget');
});

it('does not surface a stall tag touched within the last 3 days', function () {
    $lead = coldCallLeadForNextAction(['status' => LeadStatus::Contacted, 'stall_reason' => StallReason::Budget]);
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::Connected,
        'called_at' => now()->subHours(2),
    ]);

    expect($this->advisor->hintFor($lead)['label'])->not->toContain('Stalling');
});

it('flags an overdue follow-up', function () {
    $lead = coldCallLeadForNextAction(['status' => LeadStatus::New, 'next_follow_up_at' => now()->subDay()]);

    $hint = $this->advisor->hintFor($lead);

    expect($hint['label'])->toBe('🔴 Follow up now — overdue');
});

it('flags a follow-up due today (not yet overdue)', function () {
    $lead = coldCallLeadForNextAction(['status' => LeadStatus::New, 'next_follow_up_at' => now()->addHours(2)]);

    expect($this->advisor->hintFor($lead)['label'])->toBe('🟡 Follow up today');
});

it('a stalling lead outranks its own overdue follow-up', function () {
    $createdAt = now()->subDays(5);
    $lead = coldCallLeadForNextAction([
        'status' => LeadStatus::Contacted,
        'stall_reason' => StallReason::Trust,
        'next_follow_up_at' => now()->subDay(),
        'created_at' => $createdAt,
    ]);
    backdateLeadCreation($lead, $createdAt);

    expect($this->advisor->hintFor($lead)['label'])->toContain('Stalling');
});

it('flags an upcoming meeting within 24 hours', function () {
    $lead = coldCallLeadForNextAction();
    Meeting::factory()->create([
        'meetable_type' => Lead::class, 'meetable_id' => $lead->id,
        'title' => 'Discuss audit report', 'occurred_at' => now()->addHours(3),
    ]);

    $hint = $this->advisor->hintFor($lead);

    expect($hint['label'])->toStartWith('📅 Meeting:')
        ->and($hint['detail'])->toBe('Discuss audit report');
});

it('ignores a meeting more than 24 hours away', function () {
    $lead = coldCallLeadForNextAction();
    Meeting::factory()->create([
        'meetable_type' => Lead::class, 'meetable_id' => $lead->id,
        'occurred_at' => now()->addDays(3),
    ]);

    expect($this->advisor->hintFor($lead)['label'])->not->toStartWith('📅');
});

it('asks for a website/GBP link once a goal needing one is captured but no link exists', function () {
    $lead = coldCallLeadForNextAction(['goal' => LeadGoal::GrowBusiness, 'website_url' => null, 'gbp_url' => null]);

    expect($this->advisor->hintFor($lead)['label'])->toBe('🌐 Ask for Website/GBP link');
});

it('does not ask for a link once one is already captured', function () {
    $lead = coldCallLeadForNextAction(['goal' => LeadGoal::GrowBusiness, 'website_url' => 'https://example.com', 'gbp_url' => null]);

    expect($this->advisor->hintFor($lead)['label'])->not->toBe('🌐 Ask for Website/GBP link');
});

it('flags a Not Sure goal as needing Sales', function () {
    $lead = coldCallLeadForNextAction(['goal' => LeadGoal::NotSure]);

    expect($this->advisor->hintFor($lead)['label'])->toBe('🎓 Needs Sales — book a meeting');
});

it('flags an overdue welcome-message reply', function () {
    $lead = coldCallLeadForNextAction(['welcome_message_sent_at' => now()->subHours(7)]);

    expect($this->advisor->hintFor($lead)['label'])->toBe('💬 Send a WhatsApp check-in');
});

it('recommends a best-hour-to-call badge for a never-called lead', function () {
    $rep = User::factory()->create();
    foreach ([9, 11] as $hour) {
        for ($i = 1; $i <= 15; $i++) {
            CallLog::factory()->create([
                'user_id' => $rep->id,
                'direction' => CallDirection::Outgoing,
                'outcome' => CallOutcome::Connected,
                'called_at' => Carbon::now('Asia/Kolkata')->subDays($i)->setTime($hour, 0, 0)->utc(),
            ]);
        }
    }
    $lead = coldCallLeadForNextAction();

    expect($this->advisor->hintFor($lead)['label'])->toStartWith('📞');
});

it('does not recommend a call-timing badge once the lead has been called', function () {
    $lead = coldCallLeadForNextAction();
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::NoAnswer,
        'called_at' => now()->subHour(),
    ]);

    expect($this->advisor->hintFor($lead)['label'])->not->toStartWith('📞');
});

it('falls back to a gloss of the latest note when no rule fires', function () {
    $lead = coldCallLeadForNextAction();
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::NoAnswer,
        'called_at' => now()->subHour(),
    ]);
    $noteBody = 'Connect at 5pm today re: audit report';
    Note::factory()->create(['notable_type' => Lead::class, 'notable_id' => $lead->id, 'body' => $noteBody]);

    $hint = $this->advisor->hintFor($lead->fresh());

    expect($hint['label'])->toBe($noteBody)
        ->and($hint['detail'])->toBe($noteBody);
});

it('truncates a long fallback note in the label but keeps the full text in the detail tooltip', function () {
    $lead = coldCallLeadForNextAction();
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::NoAnswer,
        'called_at' => now()->subHour(),
    ]);
    $longBody = 'He said we will connect today at 5pm to discuss the full audit report and next steps in detail';
    Note::factory()->create(['notable_type' => Lead::class, 'notable_id' => $lead->id, 'body' => $longBody]);

    $hint = $this->advisor->hintFor($lead->fresh());

    expect(mb_strlen($hint['label']))->toBeLessThan(mb_strlen($longBody))
        ->and($hint['detail'])->toBe($longBody);
});

it('falls back to "no activity yet" when there is truly nothing', function () {
    $lead = coldCallLeadForNextAction();
    CallLog::factory()->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id,
        'direction' => CallDirection::Outgoing, 'outcome' => CallOutcome::NoAnswer,
        'called_at' => now()->subHour(),
    ]);

    expect($this->advisor->hintFor($lead)['label'])->toBe('— No activity yet');
});

it('never surfaces a follow-up/stall rule for a closed lead', function () {
    $lead = coldCallLeadForNextAction([
        'status' => LeadStatus::Lost,
        'stall_reason' => StallReason::Budget,
        'next_follow_up_at' => now()->subDay(),
    ]);

    $hint = $this->advisor->hintFor($lead);

    expect($hint['label'])->not->toContain('Stalling')->not->toContain('Follow up');
});
