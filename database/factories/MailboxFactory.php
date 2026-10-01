<?php

namespace Database\Factories;

use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Enums\SendPolicy;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => MailboxProvider::Gmail,
            'email_address' => fake()->unique()->userName().'@'.fake()->domainName(),
            'provider_user_id' => (string) fake()->randomNumber(9),
            'access_token' => 'ya29.test-access-token',
            'refresh_token' => '1//test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'status' => MailboxStatus::Active,
            'history_id' => '1000',
            'watch_expires_at' => now()->addDays(7),
            'send_policy' => SendPolicy::Off,
        ];
    }

    public function needsReauth(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MailboxStatus::NeedsReauth,
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MailboxStatus::Disconnected,
            'access_token' => null,
            'refresh_token' => null,
            'watch_expires_at' => null,
        ]);
    }
}
