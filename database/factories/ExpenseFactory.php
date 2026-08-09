<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => ExpenseCategory::Other->value,
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->numberBetween(5000, 500000), // paise
            'expense_date' => now()->toDateString(),
            'notes' => null,
        ];
    }
}
