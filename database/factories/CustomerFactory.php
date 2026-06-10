<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'gstin' => null,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('98########'),
            'website' => fake()->url(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'Maharashtra',
            'state_code' => '27',
            'pincode' => fake()->numerify('4#####'),
            'tags' => [],
            'owner_id' => null,
            'status' => CustomerStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CustomerStatus::Inactive]);
    }

    public function ownedBy(int $userId): static
    {
        return $this->state(fn () => ['owner_id' => $userId]);
    }
}
