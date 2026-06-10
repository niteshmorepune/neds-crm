<?php

namespace Database\Factories;

use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReport>
 */
class DailyReportFactory extends Factory
{
    protected $model = DailyReport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'tasks_completed' => fake()->numberBetween(0, 8),
            'calls_made' => fake()->numberBetween(0, 15),
            'leads_touched' => fake()->numberBetween(0, 5),
            'attendance_status' => 'present',
            'summary' => fake()->paragraph(),
            'submitted_at' => now(),
        ];
    }
}
