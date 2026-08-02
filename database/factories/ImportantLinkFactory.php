<?php

namespace Database\Factories;

use App\Models\ImportantLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportantLink>
 */
class ImportantLinkFactory extends Factory
{
    protected $model = ImportantLink::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'label' => fake()->words(2, true),
            'url' => fake()->url(),
            'sort_order' => 0,
            'created_by' => null,
        ];
    }
}
