<?php

use App\Enums\ExpenseCategory;
use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('renders the reimbursement status and controls on the index and edit pages', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $owed = Expense::factory()->create(['description' => 'Still owed expense']);
    $paid = Expense::factory()->reimbursed()->create(['description' => 'Already reimbursed expense']);

    $this->actingAs($accounts)->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('Owed')
        ->assertSee('Mark paid back')
        ->assertSee('Owed to staff');

    $this->actingAs($accounts)->get(route('expenses.edit', $owed))
        ->assertOk()
        ->assertSee('Paid back on');

    $this->actingAs($accounts)->get(route('expenses.edit', $paid))
        ->assertOk()
        ->assertSee($paid->reimbursed_at->toDateString());
});

it('defaults a new expense to not-reimbursed', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->post(route('expenses.store'), [
        'category' => ExpenseCategory::Other->value,
        'description' => 'Water jar',
        'amount' => '100',
        'expense_date' => now()->toDateString(),
    ])->assertRedirect(route('expenses.index'));

    $expense = Expense::firstWhere('description', 'Water jar');
    expect($expense->isReimbursed())->toBeFalse()
        ->and($expense->reimbursed_by)->toBeNull();
});

it('stamps who confirmed it when an expense is created already paid back', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->post(route('expenses.store'), [
        'category' => ExpenseCategory::Other->value,
        'description' => 'Reimbursed on the spot',
        'amount' => '100',
        'expense_date' => now()->toDateString(),
        'reimbursed_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::firstWhere('description', 'Reimbursed on the spot');
    expect($expense->isReimbursed())->toBeTrue()
        ->and($expense->reimbursed_by)->toBe($accounts->id);
});

it('lets an admin/manager/accounts user mark an expense as paid back with one click, forbids sales', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $expense = Expense::factory()->create();

    $this->actingAs($accounts)->post(route('expenses.reimburse', $expense))->assertRedirect();

    $expense->refresh();
    expect($expense->isReimbursed())->toBeTrue()
        ->and($expense->reimbursed_at->toDateString())->toBe(now()->toDateString())
        ->and($expense->reimbursed_by)->toBe($accounts->id);

    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = Expense::factory()->create();
    $this->actingAs($sales)->post(route('expenses.reimburse', $other))->assertForbidden();
});

it('lets a manager undo a mistaken reimbursement, forbids sales', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $expense = Expense::factory()->reimbursed()->create();

    $this->actingAs($manager)->post(route('expenses.unreimburse', $expense))->assertRedirect();

    $expense->refresh();
    expect($expense->isReimbursed())->toBeFalse()
        ->and($expense->reimbursed_by)->toBeNull();

    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = Expense::factory()->reimbursed()->create();
    $this->actingAs($sales)->post(route('expenses.unreimburse', $other))->assertForbidden();
});

it('stamps the editor as reimbursed_by when adding a paid-back date via Edit', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $expense = Expense::factory()->create();

    $this->actingAs($admin)->put(route('expenses.update', $expense), [
        'category' => $expense->category->value,
        'description' => $expense->description,
        'amount' => Money::toRupees($expense->amount),
        'expense_date' => $expense->expense_date->toDateString(),
        'reimbursed_at' => now()->toDateString(),
    ])->assertRedirect();

    $expense->refresh();
    expect($expense->isReimbursed())->toBeTrue()
        ->and($expense->reimbursed_by)->toBe($admin->id);
});

it('clears reimbursed_by when the paid-back date is removed via Edit', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $expense = Expense::factory()->reimbursed()->create();

    $this->actingAs($admin)->put(route('expenses.update', $expense), [
        'category' => $expense->category->value,
        'description' => $expense->description,
        'amount' => Money::toRupees($expense->amount),
        'expense_date' => $expense->expense_date->toDateString(),
        'reimbursed_at' => '',
    ])->assertRedirect();

    $expense->refresh();
    expect($expense->isReimbursed())->toBeFalse()
        ->and($expense->reimbursed_by)->toBeNull();
});

it('does not disturb an existing reimbursed_by when editing an unrelated field', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $originalConfirmer = User::factory()->role(UserRole::Manager)->create();
    $expense = Expense::factory()->create([
        'reimbursed_at' => '2026-09-01',
        'reimbursed_by' => $originalConfirmer->id,
        'description' => 'Old description',
    ]);

    $this->actingAs($accounts)->put(route('expenses.update', $expense), [
        'category' => $expense->category->value,
        'description' => 'New description',
        'amount' => Money::toRupees($expense->amount),
        'expense_date' => $expense->expense_date->toDateString(),
        'reimbursed_at' => '2026-09-01',
    ])->assertRedirect();

    $expense->refresh();
    expect($expense->description)->toBe('New description')
        ->and($expense->reimbursed_by)->toBe($originalConfirmer->id);
});

it('filters the list by reimbursement status and totals what is still owed to staff', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Expense::factory()->create(['description' => 'Owed one', 'amount' => 10000, 'expense_date' => '2026-08-05']);
    Expense::factory()->create(['description' => 'Owed two', 'amount' => 5000, 'expense_date' => '2026-08-06']);
    Expense::factory()->reimbursed()->create(['description' => 'Already paid', 'amount' => 20000, 'expense_date' => '2026-08-07']);

    $response = $this->actingAs($accounts)->get(route('expenses.index', ['month' => '2026-08', 'status' => 'not_reimbursed']));

    $response->assertOk()
        ->assertSee('Owed one')
        ->assertSee('Owed two')
        ->assertDontSee('Already paid')
        ->assertViewHas('total', 15000)
        ->assertViewHas('totalOwed', 15000);

    $reimbursedOnly = $this->actingAs($accounts)->get(route('expenses.index', ['month' => '2026-08', 'status' => 'reimbursed']));
    $reimbursedOnly->assertOk()
        ->assertSee('Already paid')
        ->assertDontSee('Owed one')
        ->assertViewHas('total', 20000)
        ->assertViewHas('totalOwed', 15000);
});
