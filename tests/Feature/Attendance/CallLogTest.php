<?php

use App\Enums\UserRole;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditTouch;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->sales = User::factory()->role(UserRole::Sales)->create();
});

it('logs a call against a client and returns to the client', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'duration_minutes' => 5,
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('clients.show', $customer));

    $call = CallLog::firstOrFail();
    expect($call->user_id)->toBe($this->sales->id)
        ->and($call->callable_type)->toBe(Customer::class)
        ->and($call->callable_id)->toBe($customer->id);

    // Appears in the client's timeline relation.
    expect($customer->callLogs()->count())->toBe(1);
});

it('logs a call against a lead', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'incoming',
        'outcome' => 'follow_up_needed',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('leads.show', $lead));

    expect($lead->callLogs()->count())->toBe(1);
});

it('shows a staff member only their own calls but managers all', function () {
    $other = User::factory()->role(UserRole::Sales)->create();
    CallLog::factory()->create(['user_id' => $this->sales->id, 'notes' => 'mine call']);
    CallLog::factory()->create(['user_id' => $other->id, 'notes' => 'other call']);

    $this->actingAs($this->sales)->get(route('calls.index'))->assertOk()
        ->assertSee('mine call')->assertDontSee('other call');

    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('calls.index'))->assertOk()
        ->assertSee('mine call')->assertSee('other call');
});

it('filters calls by outcome', function () {
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'connected', 'notes' => 'connected one']);
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'busy', 'notes' => 'busy one']);

    $this->actingAs($this->sales)->get(route('calls.index', ['outcome' => 'busy']))->assertOk()
        ->assertSee('busy one')->assertDontSee('connected one');
});

it('shows the follow-up reminder section expanded by default on the Log a Call form, regardless of outcome', function () {
    // Regression: it used to auto-expand only for No Answer/Busy/Follow-up
    // Needed, staying collapsed for Connected -- exactly the outcome most
    // likely to end in an unstated promise ("I'll send a proposal").
    $html = $this->actingAs($this->sales)->get(route('calls.create'))->getContent();

    expect($html)->toContain('showFollowUp: true');
});

it('renders x-data as one well-formed attribute, never leaking its JS as visible page text', function () {
    // Real bug, 2026-08-31: a literal double-quote inside a JS comment
    // inside x-data (the same "(\"I'll send a proposal\")" phrase the test
    // above's own comment references) prematurely closed the double-quoted
    // x-data="..." attribute, corrupting the whole <form> tag -- everything
    // from the next arrow function's `=>` onward (the browser's HTML parser
    // reads a bare `>` as ending the tag) rendered as literal, garbled page
    // text above the form instead of running as Alpine JS.
    // assertSee()-style string checks can't catch this -- the JS text is
    // present in the raw HTML either way, correct or broken. Only a real DOM
    // parse proves whether it landed inside the attribute or spilled into
    // the page body, which is what actually differs.
    $html = $this->actingAs($this->sales)->get(route('calls.create'))->getContent();

    libxml_use_internal_errors(true);
    $dom = new DOMDocument;
    $dom->loadHTML($html);
    libxml_clear_errors();

    // The layout has several other <form> tags (logout, filters, etc.) --
    // find the Log a Call one specifically by its real submit action.
    $xpath = new DOMXPath($dom);
    $forms = $xpath->query("//form[contains(@action, '".route('calls.store')."')]");
    expect($forms->length)->toBe(1);

    $form = $forms->item(0);
    $xData = $form->getAttribute('x-data');
    // Content near the very end of the intended x-data block -- only present
    // in the parsed attribute if the whole thing survived as one attribute.
    expect($xData)->toContain('toggleDictation')
        ->and($xData)->toContain('audioChunks');

    expect($form->textContent)->not->toContain('this.audioChunks.push');
});

it('filters to calls that need a follow-up review: connected/follow-up-needed, has notes, no reminder set', function () {
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'connected', 'notes' => 'needs review, no reminder']);
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'connected', 'notes' => 'already has one', 'follow_up_at' => now()->addDay()]);
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'connected', 'notes' => null]);
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'no_answer', 'notes' => 'never even connected']);

    $this->actingAs($this->sales)->get(route('calls.index', ['needs_followup_review' => '1']))->assertOk()
        ->assertSee('needs review, no reminder')
        ->assertDontSee('already has one')
        ->assertDontSee('never even connected');
});

it('renders the call create and index pages', function () {
    $this->actingAs($this->sales)->get(route('calls.index'))->assertOk()->assertSee('Calling');
    $this->actingAs($this->sales)->get(route('calls.create'))->assertOk()->assertSee('Log a Call');
});

it('offers browser dictation on the call notes field', function () {
    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertSee('Dictate')
        ->assertSee('toggleDictation', false)
        ->assertSee('SpeechRecognition', false);
});

it('shows the best-time-to-call hint on the create form once enough data exists', function () {
    foreach ([9, 10, 12, 11, 14, 16] as $hour) {
        for ($i = 1; $i <= 15; $i++) {
            $calledAt = now('Asia/Kolkata')->subDays($i)->setTime($hour, 0, 0)->utc();
            CallLog::factory()->create([
                'direction' => 'outgoing',
                'outcome' => in_array($hour, [9, 10, 12], true) ? 'connected' : 'no_answer',
                'called_at' => $calledAt,
            ]);
        }
    }

    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertSee('Best time to call:')
        ->assertSee('9 AM');
});

it('does not show the best-time-to-call hint when there is not enough data yet', function () {
    $this->actingAs($this->sales)->get(route('calls.create'))
        ->assertOk()
        ->assertDontSee('Best time to call:');
});

it('auto-fills a smarter follow-up suggestion for a missed call, sourced from real connect-rate data', function () {
    for ($i = 1; $i <= 15; $i++) {
        $calledAt = now('Asia/Kolkata')->subDays($i)->setTime(9, 0, 0)->utc();
        CallLog::factory()->create(['direction' => 'outgoing', 'outcome' => 'connected', 'called_at' => $calledAt]);
    }

    $response = $this->actingAs($this->sales)->get(route('calls.create'))->assertOk();

    $response->assertDontSee('suggestedFollowUp: null', false);
    $response->assertSee('Suggested from real connect-rate data', false);
});

it('leaves the follow-up suggestion null when there is no qualifying data', function () {
    $response = $this->actingAs($this->sales)->get(route('calls.create'))->assertOk();

    $response->assertSee('suggestedFollowUp: null', false);
});

it('does not let support log a call against a lead', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($support)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('lead_id');

    expect(CallLog::count())->toBe(0);
});

it('still lets support log a call against a client', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create();

    $this->actingAs($support)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('clients.show', $customer));

    expect(CallLog::count())->toBe(1);
});

it('does not offer the lead dropdown to support on the log-a-call form', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['name' => 'Hidden Lead']);

    $this->actingAs($support)->get(route('calls.create'))
        ->assertOk()
        ->assertDontSee('Hidden Lead')
        ->assertDontSee('…or Lead');
});

it('clears an earlier pending follow-up reminder once the same client is actually reached again', function () {
    $customer = Customer::factory()->create();
    $earlier = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
        'outcome' => 'no_answer',
        'follow_up_at' => now()->addDay(),
        'follow_up_notified_at' => null,
    ]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('clients.show', $customer));

    expect($earlier->fresh()->follow_up_at)->toBeNull();
});

it('clears the earlier reminder even when the new call happens after the reminder date already passed', function () {
    $customer = Customer::factory()->create();
    $earlier = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
        'outcome' => 'follow_up_needed',
        'follow_up_at' => now()->subDay(), // already overdue
        'follow_up_notified_at' => null,
    ]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect($earlier->fresh()->follow_up_at)->toBeNull();
});

it('does NOT clear an earlier reminder when the new call fails to reach the client', function () {
    $customer = Customer::factory()->create();
    $earlier = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
        'outcome' => 'no_answer',
        'follow_up_at' => now()->addDay(),
        'follow_up_notified_at' => null,
    ]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'busy', // still didn't reach them
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect($earlier->fresh()->follow_up_at)->not->toBeNull();
});

it('clears a reminder even after it has already fired (notified)', function () {
    // A reminder that already fired but was left set (because
    // follow_up_notified_at wasn't null) used to get stuck showing as
    // "overdue" forever on the Dashboard/Calling page, even once the
    // client was reached again — those views don't distinguish
    // notified from not-yet-notified, only "is follow_up_at set and past".
    $customer = Customer::factory()->create();
    $earlier = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
        'outcome' => 'no_answer',
        'follow_up_at' => now()->subDay(),
    ]);
    $earlier->forceFill(['follow_up_notified_at' => now()->subHours(2)])->save();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect($earlier->fresh()->follow_up_at)->toBeNull();
});

it('auto-logs a manual_outreach touch when the call reaches a lead in the Visibility Audit cohort', function () {
    Queue::fake();
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('leads.show', $lead));

    $touch = VisibilityAuditTouch::firstOrFail();
    expect($touch->lead_id)->toBe($lead->id)
        ->and($touch->touch_type)->toBe(VisibilityAuditTouchType::ManualOutreach)
        ->and($touch->channel)->toBe(VisibilityAuditTouchChannel::StaffCall)
        ->and($touch->actor_user_id)->toBe($this->sales->id)
        ->and($touch->success)->toBeTrue();
});

it('also logs a touch for a lead with an existing funnel event even if its service tag changed since', function () {
    Queue::fake();
    $lead = Lead::factory()->create();
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(VisibilityAuditTouch::where('lead_id', $lead->id)->exists())->toBeTrue();
});

it('does not log a touch for a call against a lead outside the Visibility Audit cohort', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(VisibilityAuditTouch::count())->toBe(0);
});

it('does not log a touch for a VA-cohort lead when the call did not reach them', function () {
    Queue::fake();
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.uniqid(), 'service_id' => $gmb->id]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'no_answer',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(VisibilityAuditTouch::count())->toBe(0);
});

it('does not log a Visibility Audit touch for a call against a client', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(VisibilityAuditTouch::count())->toBe(0);
});

it('does not touch another client\'s pending reminder', function () {
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    $otherReminder = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customerB->id,
        'outcome' => 'no_answer',
        'follow_up_at' => now()->addDay(),
        'follow_up_notified_at' => null,
    ]);

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customerA->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect($otherReminder->fresh()->follow_up_at)->not->toBeNull();
});

it('lets the rep who logged a call delete it, redirecting back to the client', function () {
    $customer = Customer::factory()->create();
    $call = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Customer::class,
        'callable_id' => $customer->id,
    ]);

    $this->actingAs($this->sales)->delete(route('calls.destroy', $call))
        ->assertRedirect(route('clients.show', $customer))
        ->assertSessionHas('status', 'Call log deleted.');

    expect(CallLog::find($call->id))->toBeNull();
});

it('redirects back to the lead when deleting a call logged against a lead', function () {
    $lead = Lead::factory()->create();
    $call = CallLog::factory()->create([
        'user_id' => $this->sales->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
    ]);

    $this->actingAs($this->sales)->delete(route('calls.destroy', $call))
        ->assertRedirect(route('leads.show', $lead));
});

it('forbids one rep from deleting another rep\'s call log', function () {
    $otherSales = User::factory()->role(UserRole::Sales)->create();
    $call = CallLog::factory()->create(['user_id' => $otherSales->id]);

    $this->actingAs($this->sales)->delete(route('calls.destroy', $call))->assertForbidden();

    expect(CallLog::find($call->id))->not->toBeNull();
});

it('lets an admin delete any rep\'s call log', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $call = CallLog::factory()->create(['user_id' => $this->sales->id]);

    $this->actingAs($admin)->delete(route('calls.destroy', $call))->assertRedirect();

    expect(CallLog::find($call->id))->toBeNull();
});
