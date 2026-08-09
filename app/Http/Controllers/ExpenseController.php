<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daily office expenses (tea, travel, stationery, internet, fuel, ...) —
 * straightforward CRUD, no approval workflow, matching Subscriptions'
 * shape. Confirmed with the owner: Admin/Manager/Accounts log directly.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $month = $request->string('month')->trim()->value();
        if ($month !== '' && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = '';
        }

        $category = $request->string('category')->trim()->value();
        if ($category !== '' && ! in_array($category, ExpenseCategory::values(), true)) {
            $category = '';
        }

        $query = Expense::query()
            ->with('user')
            ->when($month, function ($q) use ($month) {
                [$year, $monthNum] = explode('-', $month);
                $q->whereYear('expense_date', $year)->whereMonth('expense_date', $monthNum);
            })
            ->when($category, fn ($q) => $q->where('category', $category));

        $total = (int) $query->clone()->sum('amount');

        $expenses = $query->latest('expense_date')->latest('id')->paginate(20)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'total' => $total,
            'month' => $month,
            'category' => $category,
            'categories' => ExpenseCategory::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Expense::class);

        return view('expenses.create', ['categories' => ExpenseCategory::cases()]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        Expense::create($request->validatedWithPaise() + ['user_id' => $request->user()->id]);

        return redirect()->route('expenses.index')->with('status', 'Expense added.');
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('expenses.edit', ['expense' => $expense, 'categories' => ExpenseCategory::cases()]);
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update($request->validatedWithPaise());

        return redirect()->route('expenses.index')->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')->with('status', 'Expense removed.');
    }
}
