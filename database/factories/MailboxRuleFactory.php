<?php

namespace Database\Factories;

use App\Enums\SenderAccess;
use App\Models\Mailbox;
use App\Models\MailboxRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<MailboxRule>
 */
class MailboxRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'domain' => fake()->unique()->domainName(),
            'access' => SenderAccess::LearnAndAnswer,
            'creates_colleagues' => false,
        ];
    }
}
