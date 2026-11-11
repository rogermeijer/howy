<?php

namespace Database\Factories;

use App\Models\Colleague;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<Colleague>
 */
class ColleagueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => strtolower(fake()->unique()->safeEmail()),
            'name' => fake()->name(),
            'receives_mail' => true,
        ];
    }

    public function optedOut(): static
    {
        return $this->afterMaking(fn (Colleague $colleague) => $colleague->forceFill(['opted_out_at' => now()]));
    }
}
