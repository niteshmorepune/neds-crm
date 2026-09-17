<x-app-layout>
    <x-slot name="header">Expenses</x-slot>

    <div class="max-w-6xl mx-auto space-y-4">
        @if (session('status'))
            <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
                <select name="category" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $option)
                        <option value="{{ $option->value }}" @selected($category === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Paid back or owed</option>
                    <option value="not_reimbursed" @selected($status === 'not_reimbursed')>Owed to staff</option>
                    <option value="reimbursed" @selected($status === 'reimbursed')>Paid back</option>
                </select>
                <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Filter</button>
                @if ($month || $category || $status)
                    <a href="{{ route('expenses.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
                @endif
            </form>
            <a href="{{ route('expenses.create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">+ New Expense</a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Total{{ $month || $category || $status ? ' (filtered)' : '' }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ \App\Support\Money::format($total) }}</p>
            </div>
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Owed to staff{{ $month || $category ? ' (filtered)' : '' }}</p>
                <p class="mt-1 text-2xl font-semibold {{ $totalOwed > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ \App\Support\Money::format($totalOwed) }}</p>
            </div>
        </div>

        <div class="overflow-hidden overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3">Logged by</th>
                        <th class="px-4 py-3">Paid back</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($expenses as $expense)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $expense->expense_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $expense->category->label() }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $expense->description }}
                                @if ($expense->notes)
                                    <span class="block text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($expense->notes, 80) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">{{ \App\Support\Money::format($expense->amount) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $expense->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($expense->isReimbursed())
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                        Paid {{ $expense->reimbursed_at->format('d M Y') }}
                                    </span>
                                    @if ($expense->reimbursedBy)
                                        <span class="block text-xs text-gray-400">by {{ $expense->reimbursedBy->name }}</span>
                                    @endif
                                    <form method="POST" action="{{ route('expenses.unreimburse', $expense) }}" class="mt-1">
                                        @csrf
                                        <button class="text-xs text-gray-400 hover:text-gray-600 hover:underline">Undo</button>
                                    </form>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Owed</span>
                                    <form method="POST" action="{{ route('expenses.reimburse', $expense) }}" class="mt-1">
                                        @csrf
                                        <button class="text-xs text-indigo-600 hover:underline">Mark paid back</button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('expenses.edit', $expense) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline ml-3"
                                      onsubmit="return confirm('Remove this expense?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:text-red-500">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No expenses recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $expenses->links() }}</div>
    </div>
</x-app-layout>
