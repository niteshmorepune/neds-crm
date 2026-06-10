<?php

namespace Database\Factories;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Models\CallLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallLog>
 */
class CallLogFactory extends Factory
{
    protected $model = CallLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'callable_type' => null,
            'callable_id' => null,
            'direction' => CallDirection::Outgoing,
            'duration_minutes' => fake()->numberBetween(1, 30),
            'outcome' => CallOutcome::Connected,
            'notes' => fake()->sentence(),
            'called_at' => now(),
        ];
    }
}
