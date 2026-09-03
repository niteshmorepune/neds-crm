<?php

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\NextActionSnooze;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\NextAction\SupportNewTicketReplySource;

function supportNewTicketReplySource(): SupportNewTicketReplySource
{
    return app(SupportNewTicketReplySource::class);
}

it('returns null for a non-Support user even with a qualifying ticket', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    Ticket::factory()->create(['assignee_id' => $sales->id]);

    expect(supportNewTicketReplySource()->next($sales))->toBeNull();
});

it('prompts a Support agent to respond to their oldest open, unreplied ticket', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    Ticket::factory()->for($customer)->create(['assignee_id' => $support->id, 'subject' => 'Newer', 'created_at' => now()->subHour()]);
    $older = Ticket::factory()->for($customer)->create(['assignee_id' => $support->id, 'subject' => 'Website is down', 'created_at' => now()->subDay()]);

    $action = supportNewTicketReplySource()->next($support);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($older->id);
    expect($action->title)->toBe('Respond to: Website is down');
    expect($action->body)->toContain('Curamind');
    expect($action->actionUrl)->toBe(route('tickets.show', $older));
});

it('excludes a ticket that already has a staff reply', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id]);
    TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $support->id]);

    expect(supportNewTicketReplySource()->next($support))->toBeNull();
});

it('still prompts when only the customer has replied (no staff reply yet)', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id]);
    TicketReply::factory()->create(['ticket_id' => $ticket->id, 'user_id' => null, 'contact_id' => Contact::factory()->create()->id]);

    expect(supportNewTicketReplySource()->next($support)?->subjectId)->toBe($ticket->id);
});

it('excludes a ticket that is not still Open', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['assignee_id' => $support->id, 'status' => TicketStatus::InProgress]);

    expect(supportNewTicketReplySource()->next($support))->toBeNull();
});

it("does not surface another agent's ticket", function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $other = User::factory()->role(UserRole::Support)->create();
    Ticket::factory()->create(['assignee_id' => $other->id]);

    expect(supportNewTicketReplySource()->next($support))->toBeNull();
});

it('excludes a snoozed ticket but includes it again once the snooze expires', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id]);

    NextActionSnooze::create([
        'user_id' => $support->id,
        'source_key' => 'support_new_ticket_reply',
        'subject_type' => Ticket::class,
        'subject_id' => $ticket->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(supportNewTicketReplySource()->next($support))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(supportNewTicketReplySource()->next($support)?->subjectId)->toBe($ticket->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $support->id]);

    supportNewTicketReplySource()->complete($support, $ticket->id);
})->throws(RuntimeException::class);
