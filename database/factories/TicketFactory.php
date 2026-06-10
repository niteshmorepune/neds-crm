<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\SlaCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $priority = TicketPriority::Normal;
        $created = Carbon::now();

        return [
            'customer_id' => Customer::factory(),
            'subject' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'priority' => $priority,
            'status' => TicketStatus::Open,
            'sla_due_at' => app(SlaCalculator::class)->dueAt($created, $priority->slaHours()),
        ];
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority]);
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function breached(): static
    {
        return $this->state(fn () => ['sla_due_at' => Carbon::now()->subHour(), 'status' => TicketStatus::Open]);
    }
}
