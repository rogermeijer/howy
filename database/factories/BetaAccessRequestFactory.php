<?php

namespace Database\Factories;

use App\Models\BetaAccessRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BetaAccessRequest>
 */
class BetaAccessRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'locale' => 'en',
        ];
    }

    public function invited(): static
    {
        return $this->state(fn (): array => ['invited_at' => now()]);
    }

    public function redeemed(): static
    {
        return $this->state(fn (): array => ['invited_at' => now(), 'redeemed_at' => now()]);
    }
}
