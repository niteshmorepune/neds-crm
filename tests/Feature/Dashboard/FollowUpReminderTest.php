<?php

use App\Livewire\MyFollowUpReminders;
use App\Models\Customer;
use App\Models\FollowUpReminder;
use App\Models\User;
use App\Notifications\FollowUpReminderDue;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// ──────────────────────────────────────────────────────────────────────────────
// Livewire widget
// ──────────────────────────────────────────────────────────────────────────────

it('creates a reminder for the authenticated user in Asia/Kolkata time', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['company_name' => 'Ganesh Auto Parts']);

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->call('toggleForm')
        ->set('customer_id', $customer->id)
        ->set('remind_at', '2026-08-15T14:30')
        ->set('next_action', 'Call back about the SEO renewal')
        ->call('save')
        ->assertHasNoErrors();

    $reminder = FollowUpReminder::firstWhere('next_action', 'Call back about the SEO renewal');
    expect($reminder)->not->toBeNull()
        ->and($reminder->user_id)->toBe($user->id)
        ->and($reminder->customer_id)->toBe($customer->id)
        ->and($reminder->remind_at->toIso8601String())->toBe('2026-08-15T09:00:00+00:00'); // 14:30 IST = 09:00 UTC
});

it('requires a next action and a remind-on time', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->call('save')
        ->assertHasErrors(['remind_at', 'next_action']);
});

it('allows a reminder with no client selected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->set('remind_at', '2026-08-15T14:30')
        ->set('next_action', 'Follow up on a new deal')
        ->call('save')
        ->assertHasNoErrors();

    expect(FollowUpReminder::firstWhere('next_action', 'Follow up on a new deal')->customer_id)->toBeNull();
});

it('shows only the viewer\'s own pending reminders, ordered soonest first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $later = FollowUpReminder::factory()->for($user)->create(['next_action' => 'Send report', 'remind_at' => now()->addDays(3)]);
    $sooner = FollowUpReminder::factory()->for($user)->create(['next_action' => 'Call now', 'remind_at' => now()->addHour()]);
    FollowUpReminder::factory()->for($otherUser)->create(['next_action' => 'Not mine']);
    FollowUpReminder::factory()->for($user)->completed()->create(['next_action' => 'Already done']);

    $component = Livewire::actingAs($user)->test(MyFollowUpReminders::class);

    $component->assertSeeInOrder([$sooner->next_action, $later->next_action])
        ->assertDontSee('Not mine')
        ->assertDontSee('Already done');
});

it('marks a reminder done and removes it from the widget', function () {
    $user = User::factory()->create();
    $reminder = FollowUpReminder::factory()->for($user)->create(['next_action' => 'Send quotation']);

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->assertSee('Send quotation')
        ->call('markDone', $reminder->id)
        ->assertDontSee('Send quotation');

    expect($reminder->fresh()->completed_at)->not->toBeNull();
});

it('cannot mark another user\'s reminder done', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $reminder = FollowUpReminder::factory()->for($otherUser)->create();

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->call('markDone', $reminder->id)
        ->assertStatus(403);

    expect($reminder->fresh()->completed_at)->toBeNull();
});

it('shows a reminder as overdue once its time has passed', function () {
    $user = User::factory()->create();
    FollowUpReminder::factory()->for($user)->due()->create(['next_action' => 'Overdue call']);

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->assertSee('Overdue call')
        ->assertSee('Overdue —');
});

it('labels a reminder\'s client as "Client removed" when the customer has been deleted', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $reminder = FollowUpReminder::factory()->for($user)->create(['customer_id' => $customer->id, 'next_action' => 'Call them']);
    $customer->delete();

    Livewire::actingAs($user)->test(MyFollowUpReminders::class)
        ->assertSee('Call them')
        ->assertSee('Client removed');
});

it('renders on the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSeeLivewire('my-follow-up-reminders');
});

// ──────────────────────────────────────────────────────────────────────────────
// Due notification command
// ──────────────────────────────────────────────────────────────────────────────

it('notifies the owning user once a reminder becomes due', function () {
    Notification::fake();
    $user = User::factory()->create();
    $reminder = FollowUpReminder::factory()->for($user)->due()->create();

    $this->artisan('app:send-dashboard-followup-reminders')->assertSuccessful();

    Notification::assertSentTo($user, FollowUpReminderDue::class, fn ($n) => $n->reminder->is($reminder));
    expect($reminder->fresh()->notified_at)->not->toBeNull();
});

it('does not notify twice for the same reminder', function () {
    Notification::fake();
    $user = User::factory()->create();
    FollowUpReminder::factory()->for($user)->due()->create(['notified_at' => now()->subMinute()]);

    $this->artisan('app:send-dashboard-followup-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not notify for a reminder that is not yet due', function () {
    Notification::fake();
    $user = User::factory()->create();
    FollowUpReminder::factory()->for($user)->create(['remind_at' => now()->addHour()]);

    $this->artisan('app:send-dashboard-followup-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not notify for an already-completed reminder', function () {
    Notification::fake();
    $user = User::factory()->create();
    FollowUpReminder::factory()->for($user)->due()->completed()->create();

    $this->artisan('app:send-dashboard-followup-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});
