<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketSatisfactionRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketSatisfactionRating>
 */
class TicketSatisfactionRatingFactory extends Factory
{
    protected $model = TicketSatisfactionRating::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'contact_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => null,
        ];
    }
}
