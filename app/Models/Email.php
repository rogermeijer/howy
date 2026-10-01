<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\EmailSource;
use Database\Factories\EmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message, stored as it arrived from the mailbox provider.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $mailbox_id
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
 * @property list<array{name: string|null, email: string|null, date: string|null, date_text: string|null, text: string}>|null $quotes
 * @property list<string>|null $label_ids
 * @property list<array{filename: string, mime_type: string, size: int, attachment_id: string|null}>|null $attachments
 * @property bool $has_attachments
 * @property EmailSource $source
 * @property int|null $imported_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Mailbox|null $mailbox
 */
#[Fillable([
    'mailbox_id', 'provider_message_id', 'provider_thread_id', 'message_id_header',
    'in_reply_to', 'headers', 'subject', 'from_name', 'from_email', 'to', 'cc', 'sent_at',
    'received_at', 'snippet', 'body_text', 'body_html', 'content_html', 'content_text', 'quotes', 'label_ids', 'attachments',
    'has_attachments', 'source', 'imported_by_user_id',
])]
class Email extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<EmailFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
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
