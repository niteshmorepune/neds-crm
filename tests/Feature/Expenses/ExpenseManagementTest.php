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

it('lets admin, manager, and accounts view the expenses list, but forbids sales', function () {
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())->get(route('expenses.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('expenses.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Accounts)->create())->get(route('expenses.index'))->assertOk();
    $this->actingAs(User::factory()->role(UserRole::Sales)->create())->get(route('expenses.index'))->assertForbidden();
});

it('renders the create and edit pages', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $expense = Expense::factory()->create();

    $this->actingAs($accounts)->get(route('expenses.create'))->assertOk();
    $this->actingAs($accounts)->get(route('expenses.edit', $expense))->assertOk();
});

it('creates an expense, converting the rupee amount to paise and stamping the logged-in user', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->post(route('expenses.store'), [
        'category' => ExpenseCategory::Fuel->value,
        'description' => 'Petrol for client visit',
        'amount' => '450',
        'expense_date' => now()->toDateString(),
    ])->assertRedirect(route('expenses.index'));

    $expense = Expense::firstWhere('description', 'Petrol for client visit');
    expect($expense)->not->toBeNull()
        ->and($expense->amount)->toBe(Money::toPaise(450.0))
        ->and($expense->category)->toBe(ExpenseCategory::Fuel)
        ->and($expense->user_id)->toBe($accounts->id);
});

it('updates an expense', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $expense = Expense::factory()->create(['description' => 'Old description']);

    $this->actingAs($accounts)->put(route('expenses.update', $expense), [
        'category' => $expense->category->value,
        'description' => 'New description',
        'amount' => Money::toRupees($expense->amount),
        'expense_date' => $expense->expense_date->toDateString(),
    ])->assertRedirect(route('expenses.index'));

    expect($expense->fresh()->description)->toBe('New description');
});

it('deletes an expense', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $expense = Expense::factory()->create();

    $this->actingAs($accounts)->delete(route('expenses.destroy', $expense))->assertRedirect();

    expect(Expense::find($expense->id))->toBeNull();
});

it('forbids a sales rep from creating, editing, or deleting an expense', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $expense = Expense::factory()->create();

    $this->actingAs($sales)->post(route('expenses.store'), ['description' => 'Sneaky'])->assertForbidden();
    $this->actingAs($sales)->put(route('expenses.update', $expense), ['description' => 'Sneaky'])->assertForbidden();
    $this->actingAs($sales)->delete(route('expenses.destroy', $expense))->assertForbidden();
});

it('rejects a bad category value', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    $this->actingAs($accounts)->post(route('expenses.store'), [
        'category' => 'not-a-real-category',
        'description' => 'Bad',
        'amount' => '100',
        'expense_date' => now()->toDateString(),
    ])->assertSessionHasErrors('category');
});

it('filters the list by month and category, and totals only the filtered results', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();

    Expense::factory()->create(['category' => ExpenseCategory::Fuel->value, 'amount' => 50000, 'expense_date' => '2026-08-05', 'description' => 'August fuel']);
    Expense::factory()->create(['category' => ExpenseCategory::Tea->value, 'amount' => 10000, 'expense_date' => '2026-08-06', 'description' => 'August tea']);
    Expense::factory()->create(['category' => ExpenseCategory::Fuel->value, 'amount' => 90000, 'expense_date' => '2026-07-05', 'description' => 'July fuel']);

    $response = $this->actingAs($accounts)->get(route('expenses.index', ['month' => '2026-08', 'category' => ExpenseCategory::Fuel->value]));

    $response->assertOk()
        ->assertSee('August fuel')
        ->assertDontSee('August tea')
        ->assertDontSee('July fuel')
        ->assertViewHas('total', 50000);
});

it('ignores a malformed month filter instead of erroring', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    Expense::factory()->create(['description' => 'Visible expense']);

    $this->actingAs($accounts)
        ->get(route('expenses.index', ['month' => 'garbage']))
        ->assertOk()->assertSee('Visible expense');
});
