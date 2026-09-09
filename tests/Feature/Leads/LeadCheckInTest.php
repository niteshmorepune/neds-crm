<?php

use App\Enums\UserRole;
use App\Jobs\SendLeadCheckInJob;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('dispatches the check-in job when an authorized user sends one', function () {
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'phone' => '9876543210']);

    $this->actingAs($sales)
        ->post(route('leads.check-in.send', $lead))
        ->assertRedirect();

    Queue::assertPushed(SendLeadCheckInJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('rejects sending a check-in to a lead with no phone number', function () {
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'phone' => null]);

    $this->actingAs($sales)
        ->post(route('leads.check-in.send', $lead))
        ->assertSessionHasErrors('check_in');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('blocks a repeat check-in within 24 hours', function () {
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'phone' => '9876543210', 'last_checkin_sent_at' => now()->subHours(2)]);

    $this->actingAs($sales)
        ->post(route('leads.check-in.send', $lead))
        ->assertSessionHasErrors('check_in');

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('allows a check-in again once 24 hours have passed', function () {
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'phone' => '9876543210', 'last_checkin_sent_at' => now()->subHours(25)]);

    $this->actingAs($sales)
        ->post(route('leads.check-in.send', $lead))
        ->assertRedirect();

    Queue::assertPushed(SendLeadCheckInJob::class);
});

it('forbids a Sales rep from sending a check-in on another rep\'s lead', function () {
    Queue::fake();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $other->id, 'phone' => '9876543210']);

    $this->actingAs($sales)
        ->post(route('leads.check-in.send', $lead))
        ->assertForbidden();

    Queue::assertNotPushed(SendLeadCheckInJob::class);
});

it('shows the Send WhatsApp check-in button on the lead page when a phone is on file', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['phone' => '9876543210']);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Send WhatsApp check-in');
});

it('does not show the check-in button when the lead has no phone', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['phone' => null]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Send WhatsApp check-in');
});
