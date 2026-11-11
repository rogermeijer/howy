<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\AccountTopic;
use App\Enums\OutboundMailKind;
use App\Enums\OutboundMailStatus;
use App\Enums\OutboundSkipReason;
use App\Events\AccountChanged;
use Database\Factories\OutboundMailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A mail Howy sent, or meant to send, from a mailbox. Logged before it goes,
 * with everything needed to send it later: one that waits for its mailbox
 * stays pending, and adoption:send sends it once the mailbox is back. HowyMailer
 * writes them; nothing else sends from a mailbox.
 *
 * reference is our own Message-ID, signed (MailReferences), so a reply to it
 * is recognised as a reply to Howy; message_id_header is the one Gmail sent it
 * with, should it differ.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $mailbox_id
 * @property int|null $colleague_id
 * @property int|null $email_id
 * @property OutboundMailKind $kind
 * @property OutboundMailStatus $status
 * @property OutboundSkipReason|null $skip_reason
 * @property string $recipient
 * @property list<string>|null $cc
 * @property string|null $subject
 * @property string $text
 * @property string $html
 * @property array<string, string>|null $headers
 * @property string|null $reference
 * @property string|null $message_id_header
 * @property string|null $provider_message_id
 * @property string|null $provider_thread_id
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Mailbox|null $mailbox
 * @property-read Colleague|null $colleague
 * @property-read Email|null $email
 * @property-read Collection<int, Email> $replies
 * @property-read int|null $replies_count
 */
#[Fillable([
    'mailbox_id', 'colleague_id', 'email_id', 'kind', 'status', 'skip_reason', 'recipient', 'cc',
    'subject', 'text', 'html', 'headers', 'provider_thread_id',
])]
class OutboundMail extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<OutboundMailFactory> */
    use HasFactory;

    /**
     * An open mail log follows along: a mail logged, sent, held back or
     * failed is heard on the account's channel.
     */
    protected static function booted(): void
    {
        static::saved(function (OutboundMail $mail): void {
            if ($mail->wasRecentlyCreated || $mail->wasChanged('status')) {
                AccountChanged::dispatch($mail->account_id, AccountTopic::SentMails);
            }
        });
    }

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * @return BelongsTo<Colleague, $this>
     */
    public function colleague(): BelongsTo
    {
        return $this->belongsTo(Colleague::class);
    }

    /**
     * @return BelongsTo<Email, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * The mails that came in as a reply to this one.
     *
     * @return HasMany<Email, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OutboundMailKind::class,
            'status' => OutboundMailStatus::class,
            'skip_reason' => OutboundSkipReason::class,
            'cc' => 'array',
            'headers' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
