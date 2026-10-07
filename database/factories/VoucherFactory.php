<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Voucher::generateCode(),
            'max_uses' => 1,
        ];
    }

    public function usedUp(): static
    {
        return $this->state(fn (array $attributes): array => ['uses' => $attributes['max_uses'] ?? 1]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }
}
