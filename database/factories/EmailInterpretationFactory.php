<?php

namespace Database\Factories;

use App\Enums\InterpretationStatus;
use App\Enums\ReplyStatus;
use App\Models\Email;
use App\Models\EmailInterpretation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * account_id is deliberately absent: BelongsToAccount fills it from the context.
 *
 * @extends Factory<EmailInterpretation>
 */
class EmailInterpretationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_id' => Email::factory(),
            'status' => InterpretationStatus::Queued,
            'reply_status' => ReplyStatus::None,
        ];
    }
}
