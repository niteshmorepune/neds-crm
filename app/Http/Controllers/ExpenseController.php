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

        $status = $request->string('status')->trim()->value();
        if (! in_array($status, ['', 'reimbursed', 'not_reimbursed'], true)) {
            $status = '';
        }

        $base = Expense::query()
            ->with(['user', 'reimbursedBy'])
            ->when($month, function ($q) use ($month) {
                [$year, $monthNum] = explode('-', $month);
                $q->whereYear('expense_date', $year)->whereMonth('expense_date', $monthNum);
            })
            ->when($category, fn ($q) => $q->where('category', $category));

        // Owed-to-staff total always reflects the month/category filters but
        // never the status filter itself, otherwise switching to "Owed to
        // staff" would trivially match the grand total and "Paid back"
        // would trivially show zero.
        $totalOwed = (int) $base->clone()->whereNull('reimbursed_at')->sum('amount');

        $query = $base->clone()
            ->when($status === 'reimbursed', fn ($q) => $q->whereNotNull('reimbursed_at'))
            ->when($status === 'not_reimbursed', fn ($q) => $q->whereNull('reimbursed_at'));

        $total = (int) $query->clone()->sum('amount');

        $expenses = $query->latest('expense_date')->latest('id')->paginate(20)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'total' => $total,
            'totalOwed' => $totalOwed,
            'month' => $month,
            'category' => $category,
            'status' => $status,
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

        $data = $request->validatedWithPaise() + ['user_id' => $request->user()->id];
        if ($data['reimbursed_at'] !== null) {
            $data['reimbursed_by'] = $request->user()->id;
        }

        Expense::create($data);

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

        $data = $request->validatedWithPaise();

        // Only touch reimbursed_by when reimbursed_at is actually changing —
        // editing an unrelated field (e.g. description) must never reset who
        // originally confirmed the reimbursement.
        if ($data['reimbursed_at'] === null) {
            $data['reimbursed_by'] = null;
        } elseif ($data['reimbursed_at'] !== $expense->reimbursed_at?->toDateString()) {
            $data['reimbursed_by'] = $request->user()->id;
        } else {
            unset($data['reimbursed_by']);
        }

        $expense->update($data);

        return redirect()->route('expenses.index')->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->route('expenses.index')->with('status', 'Expense removed.');
    }

    /** One-click "paid back today" shortcut for the common case — Edit still covers backdating/corrections. */
    public function reimburse(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update([
            'reimbursed_at' => now()->toDateString(),
            'reimbursed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Marked as paid back.');
    }

    public function unreimburse(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $expense->update([
            'reimbursed_at' => null,
            'reimbursed_by' => null,
        ]);

        return back()->with('status', 'Marked as not yet paid back.');
    }
}
