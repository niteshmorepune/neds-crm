<?php

use App\Enums\MilestoneStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Livewire\MilestoneManager;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

function acceptedQuotation(): Quotation
{
    $quotation = Quotation::factory()->create([
        'status' => QuotationStatus::Accepted, 'place_of_supply_state_code' => '27',
    ]);
    $quotation->items()->create([
        'description' => 'AI automation build', 'sac_code' => '998314',
        'quantity' => 1, 'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000,
    ]);
    $quotation->refresh()->recalculateTotals();

    return $quotation->refresh();
}

it('adds milestones and enforces the 100% cap', function () {
    $quotation = acceptedQuotation();

    $component = Livewire::actingAs($this->admin)
        ->test(MilestoneManager::class, ['quotation' => $quotation, 'canManage' => true])
        ->set('title', 'Advance')->set('percentage', '40')->call('addMilestone')->assertHasNoErrors()
        ->set('title', 'On UAT')->set('percentage', '40')->call('addMilestone')->assertHasNoErrors();

    expect($quotation->milestones()->count())->toBe(2)
        ->and((int) $quotation->milestones()->where('title', 'Advance')->first()->amount)->toBe(40000);

    // 40 + 40 + 30 = 110 > 100 -> rejected.
    $component->set('title', 'Extra')->set('percentage', '30')->call('addMilestone')->assertHasErrors('percentage');
    expect($quotation->milestones()->count())->toBe(2);
});

it('generates a prorated invoice for a milestone preserving GST', function () {
    $quotation = acceptedQuotation();
    $milestone = $quotation->milestones()->create([
        'title' => 'Advance', 'percentage' => 40,
        'amount' => 40000, 'sort_order' => 0,
    ]);

    Livewire::actingAs($this->admin)
        ->test(MilestoneManager::class, ['quotation' => $quotation, 'canManage' => true])
        ->call('generate', $milestone->id);

    $milestone->refresh();
    expect($milestone->isBilled())->toBeTrue();

    $invoice = $milestone->invoice;
    // 40% of ₹1000 line = ₹400 taxable + 18% = ₹472 -> 47200 paise.
    expect($invoice->subtotal)->toBe(40000)
        ->and($invoice->total)->toBe(47200)
        ->and($invoice->quotation_id)->toBe($quotation->id)
        ->and($invoice->items()->count())->toBe(1);
});

it('does not bill the same milestone twice', function () {
    $quotation = acceptedQuotation();
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000]);

    $manager = Livewire::actingAs($this->admin)->test(MilestoneManager::class, ['quotation' => $quotation, 'canManage' => true]);
    $manager->call('generate', $milestone->id);
    $manager->call('generate', $milestone->id);

    expect(Invoice::where('quotation_id', $quotation->id)->count())->toBe(1);
});

it('defaults a new milestone to Pending and is not ready to invoice', function () {
    $quotation = acceptedQuotation();
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000])->refresh();

    expect($milestone->status)->toBe(MilestoneStatus::Pending)
        ->and($milestone->readyToInvoice())->toBeFalse();
});

it('marks a milestone Done via the manager and flags it ready to invoice', function () {
    $quotation = acceptedQuotation();
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000]);

    Livewire::actingAs($this->admin)
        ->test(MilestoneManager::class, ['quotation' => $quotation, 'canManage' => true])
        ->call('updateStatus', $milestone->id, 'done');

    $milestone->refresh();
    expect($milestone->status)->toBe(MilestoneStatus::Done)
        ->and($milestone->readyToInvoice())->toBeTrue();
});

it('a Done-but-billed milestone is no longer ready to invoice', function () {
    $quotation = acceptedQuotation();
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000, 'status' => MilestoneStatus::Done]);

    Livewire::actingAs($this->admin)
        ->test(MilestoneManager::class, ['quotation' => $quotation, 'canManage' => true])
        ->call('generate', $milestone->id);

    expect($milestone->refresh()->readyToInvoice())->toBeFalse();
});

it('blocks updating milestone status without manage permission', function () {
    $quotation = acceptedQuotation();
    $milestone = $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 40, 'amount' => 40000]);

    Livewire::actingAs($this->admin)
        ->test(MilestoneManager::class, ['quotation' => $quotation, 'canManage' => false])
        ->call('updateStatus', $milestone->id, 'done')
        ->assertForbidden();

    expect($milestone->fresh()->status)->toBe(MilestoneStatus::Pending);
});
