<?php

namespace Database\Factories;

use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Models\Mailbox;
use App\Models\OutboundMail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<OutboundMail>
 */
class OutboundMailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'kind' => OutboundMailKind::Welcome,
            'status' => OutboundMailStatus::Pending,
            'recipient' => strtolower(fake()->safeEmail()),
            'subject' => fake()->sentence(4),
            'text' => fake()->paragraph(),
            'html' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    public function sent(): static
    {
        return $this->afterMaking(fn (OutboundMail $mail) => $mail->forceFill([
            'status' => OutboundMailStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => 'sent-'.fake()->unique()->randomNumber(6),
        ]));
    }
}
