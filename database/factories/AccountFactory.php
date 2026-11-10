<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Set up by default, so a test reaches the page it asks for rather than
     * the setup wizard.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'locale' => 'en',
            'timezone' => 'UTC',
            'onboarded_at' => now(),
        ];
    }

    /**
     * An account whose administrators have not been through setup yet, as
     * registration leaves it.
     */
    public function notSetUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarded_at' => null,
        ]);
    }
}
