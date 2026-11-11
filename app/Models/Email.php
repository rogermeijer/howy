<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\AccountTopic;
use App\Enums\EmailSource;
use App\Events\AccountChanged;
use Database\Factories\EmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One message, stored as it arrived from the mailbox provider.
 *
 * A quote is one earlier message the mail carries along: quoted under a reply,
 * or forwarded (inline, or attached as a message of its own).
 *
 * @phpstan-type Quote array{name: string|null, email: string|null, date: string|null, date_text: string|null, text: string, message_id?: string|null, forwarded?: bool}
 *
 * An attachment as the mail carried it, and what Howy decided about it: new
 * to the knowledge base (and which version it became), known already (and
 * which version it is), ignored, or not readable.
 * @phpstan-type Attachment array{filename: string, mime_type: string, size: int, attachment_id: string|null, part_id?: string|null, content_id?: string|null, is_inline?: bool, sha256?: string|null, decision?: string|null, reason?: string|null, document_version_id?: int|null}
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $mailbox_id
 * @property int|null $colleague_id who sent it, when they are a colleague
 * @property int|null $outbound_mail_id the mail of Howy's it replies to
 * @property string $provider_message_id
 * @property string|null $provider_thread_id
 * @property string|null $message_id_header
 * @property string|null $in_reply_to
 * @property list<array{name: string, value: string}>|null $headers
 * @property string|null $subject
 * @property string|null $from_name
 * @property string|null $from_email
 * @property list<array{name: string|null, email: string}>|null $to
 * @property list<array{name: string|null, email: string}>|null $cc
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property string|null $snippet
 * @property string|null $body_text
 * @property string|null $body_html
 * @property string|null $content_html
 * @property string|null $content_text
 * @property list<Quote>|null $quotes
 * @property string|null $content_hash
 * @property list<string>|null $label_ids
 * @property list<Attachment>|null $attachments
 * @property bool $has_attachments
 * @property EmailSource $source
 * @property int|null $imported_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Mailbox|null $mailbox
 * @property-read Colleague|null $colleague
 * @property-read OutboundMail|null $outboundMail
 * @property-read EmailInterpretation|null $interpretation
 */
#[Fillable([
    'mailbox_id', 'provider_message_id', 'provider_thread_id', 'message_id_header',
    'in_reply_to', 'headers', 'subject', 'from_name', 'from_email', 'to', 'cc', 'sent_at',
    'received_at', 'snippet', 'body_text', 'body_html', 'content_html', 'content_text', 'quotes', 'content_hash', 'label_ids', 'attachments',
    'has_attachments', 'source', 'imported_by_user_id',
])]
class Email extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<EmailFactory> */
    use HasFactory;

    /**
     * Mail that comes in shows in an open inbox. An import of a mailbox's
     * history stays quiet: it would only be thousands of reloads.
     */
    protected static function booted(): void
    {
        static::created(function (Email $email): void {
            if ($email->source !== EmailSource::Import) {
                AccountChanged::dispatch($email->account_id, AccountTopic::Mail);
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
     * The mail of Howy's this one replies to.
     *
     * @return BelongsTo<OutboundMail, $this>
     */
    public function outboundMail(): BelongsTo
    {
        return $this->belongsTo(OutboundMail::class);
    }

    /**
     * @return HasOne<EmailInterpretation, $this>
     */
    public function interpretation(): HasOne
    {
        return $this->hasOne(EmailInterpretation::class);
    }

    /**
     * The value of a header, matched case-insensitively; the first one wins.
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers ?? [] as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                return $header['value'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'to' => 'array',
            'cc' => 'array',
            'quotes' => 'array',
            'label_ids' => 'array',
            'attachments' => 'array',
            'has_attachments' => 'boolean',
            'source' => EmailSource::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
