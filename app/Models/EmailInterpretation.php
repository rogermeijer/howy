<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\EmailIntent;
use App\Enums\InterpretationMode;
use App\Enums\InterpretationOutcome;
use App\Enums\InterpretationStatus;
use App\Enums\ReplyStatus;
use Database\Factories\EmailInterpretationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How cc: read one mail to a mailbox, and what it did about it: answered a
 * question (or suggested an answer to whoever was asked), added what was new,
 * held what contradicts the knowledge base, and whether a reply went out.
 *
 * @phpstan-type Citation array{type: 'document'|'email', id: int, title: string, section_id: int|null, heading_path: string|null, page: int|null}
 * @phpstan-type Statement array{statement: string, subject: string|null, valid_from: string|null, verdict: 'new'|'duplicate'|'conflict', fact_id: int|null, existing_fact_id: int|null, existing_statement: string|null, explanation: string|null}
 *
 * @property int $id
 * @property int $account_id
 * @property int $email_id
 * @property InterpretationStatus $status
 * @property InterpretationMode $mode
 * @property EmailIntent|null $intent
 * @property float|null $intent_confidence
 * @property InterpretationOutcome|null $outcome
 * @property string|null $summary
 * @property string|null $language
 * @property string|null $question
 * @property string|null $answer
 * @property float|null $answer_confidence
 * @property string|null $answer_gaps
 * @property list<Citation>|null $citations
 * @property list<Statement>|null $statements
 * @property ReplyStatus $reply_status
 * @property string|null $reply_text
 * @property list<string>|null $reply_recipients
 * @property string|null $reply_provider_message_id
 * @property Carbon|null $replied_at
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Email $email
 */
#[Fillable([
    'email_id', 'status', 'mode', 'intent', 'intent_confidence', 'outcome', 'summary', 'language',
    'question', 'answer', 'answer_confidence', 'answer_gaps', 'citations', 'statements', 'reply_status', 'reply_text',
    'reply_recipients',
    'reply_provider_message_id', 'replied_at', 'error', 'processed_at',
])]
class EmailInterpretation extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<EmailInterpretationFactory> */
    use HasFactory;

    /**
     * Mirror the column defaults, so a new row knows them before it is read back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'mode' => 'addressed',
        'reply_status' => 'none',
    ];

    /**
     * @return BelongsTo<Email, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InterpretationStatus::class,
            'mode' => InterpretationMode::class,
            'intent' => EmailIntent::class,
            'intent_confidence' => 'float',
            'outcome' => InterpretationOutcome::class,
            'answer_confidence' => 'float',
            'citations' => 'array',
            'reply_recipients' => 'array',
            'statements' => 'array',
            'reply_status' => ReplyStatus::class,
            'replied_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
