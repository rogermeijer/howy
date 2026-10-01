<?php

namespace Database\Factories;

use App\Enums\EmailSource;
use App\Models\Email;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $received = fake()->dateTimeBetween('-30 days');

        return [
            'provider_message_id' => Str::lower(Str::random(16)),
            'provider_thread_id' => Str::lower(Str::random(16)),
            'subject' => fake()->sentence(5),
            'from_name' => fake()->name(),
            'from_email' => fake()->safeEmail(),
            'to' => [['name' => null, 'email' => fake()->safeEmail()]],
            'cc' => [],
            'sent_at' => $received,
            'received_at' => $received,
            'snippet' => fake()->sentence(12),
            'body_text' => fake()->paragraphs(2, true),
            'label_ids' => ['INBOX'],
            'attachments' => [],
            'has_attachments' => false,
            'source' => EmailSource::Push,
        ];
    }
}
