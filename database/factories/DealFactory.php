<?php

namespace Database\Factories;

use App\Enums\DealStage;
use App\Models\Customer;
use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        return [
            'title' => fake()->catchPhrase(),
            'customer_id' => Customer::factory(),
            'service_id' => null,
            'value' => fake()->numberBetween(100000, 100000000), // paise
            'stage' => DealStage::New,
            'owner_id' => null,
            'next_follow_up_at' => null,
            'lead_id' => null,
        ];
    }

    public function stage(DealStage $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }

    public function ownedBy(int $userId): static
    {
        return $this->state(fn () => ['owner_id' => $userId]);
    }
}
