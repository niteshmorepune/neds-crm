@php($amountRupees = old('amount', isset($expense) ? \App\Support\Money::toRupees($expense->amount) : ''))

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="category" value="Category *" />
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            @foreach ($categories as $option)
                <option value="{{ $option->value }}" @selected(old('category', $expense->category->value ?? '') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="amount" value="Amount (₹) *" />
        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="$amountRupees" required />
        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
    </div>
</div>

<div>
    <x-input-label for="description" value="Description *" />
    <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" placeholder="e.g. Office tea/coffee, Auto fare to client site"
                  :value="old('description', $expense->description ?? '')" required />
    <x-input-error :messages="$errors->get('description')" class="mt-1" />
</div>

<div>
    <x-input-label for="expense_date" value="Date *" />
    <x-text-input id="expense_date" name="expense_date" type="date" class="mt-1 block w-full"
        :value="old('expense_date', isset($expense) ? $expense->expense_date->toDateString() : now()->toDateString())" required />
    <x-input-error :messages="$errors->get('expense_date')" class="mt-1" />
</div>

<div>
    <x-input-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $expense->notes ?? '') }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
</div>
