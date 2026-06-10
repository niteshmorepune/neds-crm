<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'designation' => fake()->jobTitle(),
            'phone' => fake()->numerify('98########'),
            'email' => fake()->unique()->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    /**
     * A contact with active portal access (password "password").
     */
    public function portalUser(): static
    {
        return $this->state(fn () => [
            'portal_enabled' => true,
            'password' => Hash::make('password'),
            'password_set_at' => now(),
        ]);
    }
}
