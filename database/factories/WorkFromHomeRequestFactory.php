<?php

namespace Database\Factories;

use App\Enums\WorkFromHomeRequestStatus;
use App\Enums\WorkFromHomeRequestType;
use App\Models\User;
use App\Models\WorkFromHomeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkFromHomeRequest>
 */
class WorkFromHomeRequestFactory extends Factory
{
    protected $model = WorkFromHomeRequest::class;

    public function definition(): array
    {
        $start = now()->addWeek()->startOfWeek();

        return [
            'user_id' => User::factory(),
            'type' => WorkFromHomeRequestType::FullDay,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDay()->toDateString(),
            'reason' => $this->faker->sentence(),
            'status' => WorkFromHomeRequestStatus::Pending,
        ];
    }
}
