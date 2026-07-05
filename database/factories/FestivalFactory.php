<?php

namespace Database\Factories;

use App\Models\Festival;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Festival>
 */
class FestivalFactory extends Factory
{
    protected $model = Festival::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Festival',
            'date' => now()->addDays(fake()->numberBetween(1, 60))->toDateString(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
