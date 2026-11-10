<?php

namespace Database\Factories;

use App\Enums\SenderAccess;
use App\Models\Mailbox;
use App\Models\MailboxSender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<MailboxSender>
 */
class MailboxSenderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'entry' => fake()->unique()->safeEmail(),
            'access' => SenderAccess::LearnAndAnswer,
        ];
    }
}
