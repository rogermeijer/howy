<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ColleagueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Someone who mails Howy, one per address per account: for nearly every
 * account a colleague inside the company, and so the person Howy's own mails
 * (welcome, how it works…) are for. Most never log in; user_id says when
 * one is a member of the account too.
 *
 * What Howy may do with their mail is a mailbox's rules: one of their own on
 * that mailbox (MailboxRule::colleague_id), else the rule for their domain.
 * The timestamps from welcomed_at on record where their adoption mails stand.
 *
 * @property int $id
 * @property int $account_id
 * @property string $email
 * @property string|null $name
 * @property int|null $user_id
 * @property int|null $home_mailbox_id
 * @property bool $receives_mail
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_mail_at
 * @property Carbon|null $opted_out_at
 * @property Carbon|null $welcomed_at
 * @property Carbon|null $explained_at
 * @property Carbon|null $first_saved_at
 * @property Carbon|null $nudged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read User|null $user
 * @property-read Mailbox|null $homeMailbox
 * @property-read Collection<int, MailboxRule> $rules
 * @property-read int|null $rules_count
 * @property-read Collection<int, Email> $emails
 * @property-read int|null $emails_count
 * @property-read Collection<int, OutboundMail> $outboundMails
 * @property-read int|null $outbound_mails_count
 */
#[Fillable([
    'email', 'name', 'user_id', 'home_mailbox_id', 'receives_mail', 'first_seen_at', 'last_mail_at',
])]
class Colleague extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<ColleagueFactory> */
    use HasFactory;

    /**
     * Mirrors the column default, so a new colleague knows it before it is
     * read back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'receives_mail' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function homeMailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class, 'home_mailbox_id');
    }

    /**
     * The rules of their own, one per mailbox at most.
     *
     * @return HasMany<MailboxRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(MailboxRule::class);
    }

    /**
     * @return HasMany<Email, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /**
     * What Howy sent them, or meant to.
     *
     * @return HasMany<OutboundMail, $this>
     */
    public function outboundMails(): HasMany
    {
        return $this->hasMany(OutboundMail::class);
    }

    /**
     * The name to address them by, else the address.
     */
    public function displayName(): string
    {
        return filled($this->name) ? (string) $this->name : $this->email;
    }

    public function domain(): string
    {
        return strtolower(substr((string) strrchr($this->email, '@'), 1));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receives_mail' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_mail_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'welcomed_at' => 'datetime',
            'explained_at' => 'datetime',
            'first_saved_at' => 'datetime',
            'nudged_at' => 'datetime',
        ];
    }
}
