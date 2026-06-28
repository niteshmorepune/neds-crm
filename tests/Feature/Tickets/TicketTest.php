<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Livewire\TicketReplies;
use App\Mail\SlaEscalation;
use App\Mail\TicketNotification;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->support = User::factory()->role(UserRole::Support)->create();
});

it('creates a ticket, sets an SLA due time, and emails the client', function () {
    Mail::fake();
    $customer = Customer::factory()->create(['email' => 'client@x.test']);

    $this->actingAs($this->support)->post(route('tickets.store'), [
        'customer_id' => $customer->id,
        'subject' => 'Site is down',
        'description' => 'Homepage returns 500',
        'priority' => 'urgent',
    ])->assertRedirect();

    $ticket = Ticket::firstWhere('subject', 'Site is down');
    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->sla_due_at)->not->toBeNull()
        ->and($ticket->created_by)->toBe($this->support->id);

    Mail::assertSent(TicketNotification::class, fn (TicketNotification $m) => $m->kind === 'created' && $m->hasTo('client@x.test'));
});

it('emails the client on a public reply but not an internal note', function () {
    Mail::fake();
    $ticket = Ticket::factory()->create(['customer_id' => Customer::factory()->create(['email' => 'c@x.test'])->id]);

    Livewire::actingAs($this->support)->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->set('body', 'We are looking into it')->set('is_internal', false)->call('addReply')->assertHasNoErrors();
    Mail::assertSent(TicketNotification::class, fn (TicketNotification $m) => $m->kind === 'replied');

    Mail::fake(); // reset
    Livewire::actingAs($this->support)->test(TicketReplies::class, ['ticket' => $ticket, 'canManage' => true])
        ->set('body', 'internal: escalate to dev')->set('is_internal', true)->call('addReply');
    Mail::assertNothingSent();

    expect($ticket->replies()->count())->toBe(2);
});

it('resolves a ticket and emails the client', function () {
    Mail::fake();
    $ticket = Ticket::factory()->create(['customer_id' => Customer::factory()->create(['email' => 'c@x.test'])->id]);

    $this->actingAs($this->support)->post(route('tickets.resolve', $ticket))->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Resolved)->and($ticket->resolved_at)->not->toBeNull();
    Mail::assertSent(TicketNotification::class, fn (TicketNotification $m) => $m->kind === 'resolved');
});

it('detects an SLA breach and escalates to managers', function () {
    Mail::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $breached = Ticket::factory()->breached()->create();

    expect($breached->isSlaBreached())->toBeTrue();

    $this->artisan('app:check-ticket-sla')->assertSuccessful();
    Mail::assertSent(SlaEscalation::class, fn (SlaEscalation $m) => $m->hasTo($manager->email));
});

it('does not escalate when nothing is breached', function () {
    Mail::fake();
    User::factory()->role(UserRole::Manager)->create();
    Ticket::factory()->create(); // SLA in the future

    $this->artisan('app:check-ticket-sla')->assertSuccessful();
    Mail::assertNothingSent();
});

it('restricts ticket access by role', function () {
    expect(User::factory()->role(UserRole::Accounts)->create()->can('viewAny', Ticket::class))->toBeFalse()
        ->and($this->support->can('viewAny', Ticket::class))->toBeTrue();

    // Sales only sees their own clients' tickets.
    $sales = User::factory()->role(UserRole::Sales)->create();
    $foreign = Ticket::factory()->create(['customer_id' => Customer::factory()->ownedBy(User::factory()->create()->id)->create()->id]);
    expect($sales->can('view', $foreign))->toBeFalse();
    $this->actingAs($sales)->get(route('tickets.show', $foreign))->assertForbidden();
});

it('renders ticket index, create and show pages', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs($this->support)->get(route('tickets.index'))->assertOk()->assertSee('Tickets');
    $this->actingAs($this->support)->get(route('tickets.create'))->assertOk()->assertSee('Subject');
    $this->actingAs($this->support)->get(route('tickets.show', $ticket))->assertOk()->assertSee($ticket->subject);
});

it('shows a Drishti context link on ticket show when the customer has drishti_client_id', function () {
    config(['services.drishti.base_url' => 'https://nedsdrishti.in']);
    $customer = Customer::factory()->create(['drishti_client_id' => 'drsh-99']);
    $ticket   = Ticket::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($this->support)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('https://nedsdrishti.in/clients/drsh-99');
});

it('omits the Drishti context link when the customer has no drishti_client_id', function () {
    config(['services.drishti.base_url' => 'https://nedsdrishti.in']);
    $customer = Customer::factory()->create(['drishti_client_id' => null]);
    $ticket   = Ticket::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($this->support)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('Open in Drishti');
});

it('links to the audit page for SEO and GMB service tickets', function () {
    config(['services.drishti.base_url' => 'https://nedsdrishti.in']);
    $customer = Customer::factory()->create(['drishti_client_id' => 'drsh-seo']);
    $service  = \App\Models\Service::factory()->create(['name' => 'SEO']);
    $ticket   = Ticket::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);

    $this->actingAs($this->support)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('https://nedsdrishti.in/audit/drsh-seo');
});

it('links to the optimize page for Social Media and Google Ads tickets', function () {
    config(['services.drishti.base_url' => 'https://nedsdrishti.in']);
    $customer = Customer::factory()->create(['drishti_client_id' => 'drsh-sm']);
    $service  = \App\Models\Service::factory()->create(['name' => 'Social Media']);
    $ticket   = Ticket::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);

    $this->actingAs($this->support)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertSee('https://nedsdrishti.in/optimize/drsh-sm');
});
