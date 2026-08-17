<?php

use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\MenuItem;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmployeeActivityTimeline;

beforeEach(function () {
    $this->timeline = app(EmployeeActivityTimeline::class);
    $this->user = User::factory()->role(UserRole::Sales)->create();
});

describe('entries()', function () {
    it('describes a deal stage change and links to the deal', function () {
        $deal = Deal::factory()->create(['title' => 'ADTA Group Website']);
        Activity::create([
            'user_id' => $this->user->id, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
            'event' => 'updated', 'changes' => ['stage' => 'negotiation'],
        ]);

        $entries = $this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay());

        expect($entries)->toHaveCount(1);
        expect($entries->first()['description'])->toBe('Moved deal "ADTA Group Website" to Negotiation');
        expect($entries->first()['url'])->toBe(route('deals.show', $deal));
    });

    it('describes a call log entry with direction, contact, and outcome', function () {
        $lead = Lead::factory()->create(['name' => 'Kiran Katte']);
        CallLog::factory()->create([
            'user_id' => $this->user->id, 'callable_type' => Lead::class, 'callable_id' => $lead->id,
            'direction' => 'outgoing', 'outcome' => 'connected', 'duration_minutes' => 5, 'called_at' => now(),
        ]);

        $entries = $this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay());

        expect($entries->first()['description'])->toBe('Outgoing call with Kiran Katte — Connected (5 min)');
        expect($entries->first()['type'])->toBe('Call');
    });

    it('excludes activity from another user', function () {
        $other = User::factory()->create();
        $deal = Deal::factory()->create();
        Activity::create([
            'user_id' => $other->id, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
            'event' => 'created', 'changes' => [],
        ]);

        expect($this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay()))->toBeEmpty();
    });

    it('excludes activity outside the requested date range', function () {
        $deal = Deal::factory()->create();
        $activity = Activity::create([
            'user_id' => $this->user->id, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
            'event' => 'created', 'changes' => [],
        ]);
        $activity->created_at = now()->subDays(3);
        $activity->saveQuietly();

        expect($this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay()))->toBeEmpty();
    });

    it('excludes an untracked subject type, e.g. MenuItem', function () {
        Activity::create([
            'user_id' => $this->user->id, 'subject_type' => MenuItem::class, 'subject_id' => 1,
            'event' => 'updated', 'changes' => ['sort_order' => 2],
        ]);

        expect($this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay()))->toBeEmpty();
    });

    it('falls back to a "(removed)" description with no link when the subject no longer exists', function () {
        Activity::create([
            'user_id' => $this->user->id, 'subject_type' => Task::class, 'subject_id' => 999999,
            'event' => 'deleted', 'changes' => null,
        ]);

        $entry = $this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay())->first();

        expect($entry['description'])->toBe('deleted a task (removed)');
        expect($entry['url'])->toBeNull();
    });

    it('sorts entries most recent first', function () {
        $deal = Deal::factory()->create(['title' => 'Older']);
        $older = Activity::create([
            'user_id' => $this->user->id, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
            'event' => 'created', 'changes' => [],
        ]);
        $older->created_at = now()->subHours(3);
        $older->saveQuietly();

        $ticket = Ticket::factory()->create(['subject' => 'Newer']);
        Activity::create([
            'user_id' => $this->user->id, 'subject_type' => Ticket::class, 'subject_id' => $ticket->id,
            'event' => 'created', 'changes' => [],
        ]);

        $entries = $this->timeline->entries($this->user, now()->startOfDay(), now()->endOfDay());

        expect($entries->pluck('description')->all())->toBe([
            'Logged ticket "Newer"',
            'Created deal "Older"',
        ]);
    });
});

describe('pending()', function () {
    it('includes an open task assigned to the user and excludes a done one', function () {
        Task::factory()->assignedTo($this->user->id)->status(TaskStatus::Todo)->create(['title' => 'Open task']);
        Task::factory()->assignedTo($this->user->id)->status(TaskStatus::Done)->create(['title' => 'Finished task']);

        $descriptions = $this->timeline->pending($this->user)->pluck('description')->implode('|');

        expect($descriptions)->toContain('Open task')->not->toContain('Finished task');
    });

    it('includes an open ticket assigned to the user and excludes a resolved one', function () {
        Ticket::factory()->create(['assignee_id' => $this->user->id, 'status' => TicketStatus::Open, 'subject' => 'Open ticket']);
        Ticket::factory()->create(['assignee_id' => $this->user->id, 'status' => TicketStatus::Resolved, 'subject' => 'Resolved ticket']);

        $descriptions = $this->timeline->pending($this->user)->pluck('description')->implode('|');

        expect($descriptions)->toContain('Open ticket')->not->toContain('Resolved ticket');
    });

    it('includes an open lead owned by the user and excludes a converted one', function () {
        Lead::factory()->ownedBy($this->user->id)->create(['name' => 'Open Lead', 'status' => LeadStatus::Contacted]);
        Lead::factory()->ownedBy($this->user->id)->create(['name' => 'Converted Lead', 'status' => LeadStatus::Converted]);

        $descriptions = $this->timeline->pending($this->user)->pluck('description')->implode('|');

        expect($descriptions)->toContain('Open Lead')->not->toContain('Converted Lead');
    });

    it('includes an open deal owned by the user and excludes a won one', function () {
        Deal::factory()->ownedBy($this->user->id)->create(['title' => 'Open Deal', 'stage' => DealStage::Proposal]);
        Deal::factory()->ownedBy($this->user->id)->create(['title' => 'Won Deal', 'stage' => DealStage::Won]);

        $descriptions = $this->timeline->pending($this->user)->pluck('description')->implode('|');

        expect($descriptions)->toContain('Open Deal')->not->toContain('Won Deal');
    });

    it('attributes a sent quotation to the deal owner', function () {
        $deal = Deal::factory()->ownedBy($this->user->id)->create();
        $quotation = Quotation::factory()->create(['deal_id' => $deal->id, 'status' => QuotationStatus::Sent, 'number' => 'Q-0099']);
        // Another Sent quotation on a deal owned by someone else must not appear.
        $otherDeal = Deal::factory()->ownedBy(User::factory()->create()->id)->create();
        Quotation::factory()->create(['deal_id' => $otherDeal->id, 'status' => QuotationStatus::Sent, 'number' => 'Q-0100']);

        $pending = $this->timeline->pending($this->user);

        expect($pending->pluck('description')->implode('|'))->toContain('Q-0099')->not->toContain('Q-0100');
    });

    it('attributes a quotation with no deal to the customer\'s account owner', function () {
        $customer = Customer::factory()->create(['owner_id' => $this->user->id]);
        Quotation::factory()->create(['customer_id' => $customer->id, 'deal_id' => null, 'status' => QuotationStatus::Sent, 'number' => 'Q-0201']);

        expect($this->timeline->pending($this->user)->pluck('description')->implode('|'))->toContain('Q-0201');
    });

    it('attributes an unpaid invoice to the customer\'s account owner and shows the outstanding balance', function () {
        $customer = Customer::factory()->create(['owner_id' => $this->user->id]);
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id, 'status' => InvoiceStatus::PartiallyPaid,
            'total' => 100000, 'amount_paid' => 40000,
        ]);

        $description = $this->timeline->pending($this->user)->firstWhere('type', 'Invoice')['description'];

        expect($description)->toContain($invoice->invoice_number)->toContain('₹600.00');
    });

    it('excludes a fully paid invoice', function () {
        $customer = Customer::factory()->create(['owner_id' => $this->user->id]);
        Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Paid, 'invoice_number' => 'NEDS/2026-27/9999']);

        expect($this->timeline->pending($this->user)->pluck('description')->implode('|'))->not->toContain('9999');
    });
});
