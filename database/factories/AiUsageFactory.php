<?php

namespace Database\Factories;

use App\Models\AiUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    protected $model = AiUsage::class;

    public function definition(): array
    {
        return [
            'feature' => 'lead_scoring',
            'model' => 'claude-haiku-4-5-20251001',
            'input_tokens' => $this->faker->numberBetween(100, 1000),
            'output_tokens' => $this->faker->numberBetween(50, 500),
        ];
    }
}
