<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\NextActionSnooze;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NextAction\TicketSlaAtRiskSource;

function ticketSlaAtRiskSource(): TicketSlaAtRiskSource
{
    return app(TicketSlaAtRiskSource::class);
}

it('returns null for a non-Support user', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Ticket::factory()->create(['assignee_id' => $sales->id, 'sla_due_at' => now()->addHour()]);

    expect(ticketSlaAtRiskSource()->next($sales))->toBeNull();
});

it('prompts the assignee when SLA is due within 4 hours', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    $ticket = Ticket::factory()->for($customer)->create([
        'assignee_id' => $support->id,
        'subject' => 'Website is down',
        'sla_due_at' => now()->addHours(2),
    ]);

    $action = ticketSlaAtRiskSource()->next($support);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($ticket->id);
    expect($action->title)->toBe('SLA due soon: Website is down');
    expect($action->body)->toContain('Curamind');
    expect($action->actionUrl)->toBe(route('tickets.show', $ticket));
});

it('labels an already-breached ticket differently', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create([
        'assignee_id' => $support->id,
        'subject' => 'Login broken',
        'sla_due_at' => now()->subHour(),
    ]);

    $action = ticketSlaAtRiskSource()->next($support);

    expect($action->title)->toBe('SLA breached: Login broken');
    expect($action->body)->toContain('Overdue since');
});

it('does not prompt a ticket due more than 4 hours out', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['assignee_id' => $support->id, 'sla_due_at' => now()->addHours(5)]);

    expect(ticketSlaAtRiskSource()->next($support))->toBeNull();
});

it('does not prompt a Resolved or Closed ticket even if its SLA time has passed', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['assignee_id' => $support->id, 'status' => TicketStatus::Resolved, 'sla_due_at' => now()->subHour()]);
    Ticket::factory()->create(['assignee_id' => $support->id, 'status' => TicketStatus::Closed, 'sla_due_at' => now()->subHour()]);

    expect(ticketSlaAtRiskSource()->next($support))->toBeNull();
});

it("does not surface another agent's ticket", function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $other = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['assignee_id' => $other->id, 'sla_due_at' => now()->addHour()]);

    expect(ticketSlaAtRiskSource()->next($support))->toBeNull();
});

it('picks the soonest-due at-risk ticket when more than one qualifies', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['assignee_id' => $support->id, 'sla_due_at' => now()->addHours(3)]);
    $sooner = Ticket::factory()->create(['assignee_id' => $support->id, 'sla_due_at' => now()->addHour()]);

    expect(ticketSlaAtRiskSource()->next($support)?->subjectId)->toBe($sooner->id);
});

it('excludes a snoozed ticket but includes it again once the snooze expires', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id, 'sla_due_at' => now()->addHour()]);

    NextActionSnooze::create([
        'user_id' => $support->id,
        'source_key' => 'ticket_sla_at_risk',
        'subject_type' => Ticket::class,
        'subject_id' => $ticket->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(ticketSlaAtRiskSource()->next($support))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(ticketSlaAtRiskSource()->next($support)?->subjectId)->toBe($ticket->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id, 'sla_due_at' => now()->addHour()]);

    ticketSlaAtRiskSource()->complete($support, $ticket->id);
})->throws(RuntimeException::class);
