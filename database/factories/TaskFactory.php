<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'project_id' => null,
            'assignee_id' => null,
            'created_by' => null,
            'due_date' => now()->addDays(7),
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Todo,
        ];
    }

    public function assignedTo(int $userId): static
    {
        return $this->state(fn () => ['assignee_id' => $userId]);
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
