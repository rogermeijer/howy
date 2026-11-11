<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\SenderAccess;
use Database\Factories\MailboxRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What Howy may do with mail on one mailbox, for one colleague or for one
 * whole domain: exactly one of colleague_id and domain is set (a check
 * constraint says so). A colleague's rule beats their domain's; see
 * Mailbox::access(). The mailbox's own domain is a domain rule like any other,
 * one that creates colleagues.
 *
 * @property int $id
 * @property int $account_id
 * @property int $mailbox_id
 * @property int|null $colleague_id
 * @property string|null $domain
 * @property SenderAccess $access
 * @property bool $creates_colleagues whether someone this domain rule lets in becomes a colleague
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Mailbox $mailbox
 * @property-read Colleague|null $colleague
 */
#[Fillable(['mailbox_id', 'colleague_id', 'domain', 'access', 'creates_colleagues'])]
class MailboxRule extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<MailboxRuleFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'creates_colleagues' => false,
    ];

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
     * The address or domain the rule is for, as the rules panel lists it.
     */
    public function entry(): string
    {
        return $this->domain ?? (string) $this->colleague?->email;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access' => SenderAccess::class,
            'creates_colleagues' => 'boolean',
        ];
    }
}
