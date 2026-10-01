<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Enums\SendPolicy;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A shared mailbox (support@, kennis@…) the account has connected.
 *
 * It belongs to the account, not to the person who connected it:
 * connected_by_user_id only records who authorised it with Google.
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $connected_by_user_id
 * @property MailboxProvider $provider
 * @property string $email_address
 * @property string|null $provider_user_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property MailboxStatus $status
 * @property string|null $history_id
 * @property Carbon|null $watch_expires_at
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $last_message_at
 * @property string|null $last_error
 * @property string|null $import_batch_id
 * @property SendPolicy $send_policy
 * @property list<string>|null $send_whitelist
 * @property list<string>|null $send_blacklist
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read User|null $connectedBy
 * @property-read Collection<int, Email> $emails
 * @property-read int|null $emails_count
 */
#[Fillable([
    'connected_by_user_id', 'provider', 'email_address', 'provider_user_id',
    'access_token', 'refresh_token', 'token_expires_at', 'status', 'history_id',
    'watch_expires_at', 'last_synced_at', 'last_message_at', 'last_error',
    'import_batch_id', 'send_policy', 'send_whitelist', 'send_blacklist',
])]
#[Hidden(['access_token', 'refresh_token'])]
class Mailbox extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    /**
     * Mirrors the column default, so a new mailbox knows its policy before it
     * is read back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'send_policy' => 'always',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    /**
     * @return HasMany<Email, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function isActive(): bool
    {
        return $this->status === MailboxStatus::Active;
    }

    /**
     * The part after the @, lowercased: what "the same domain" means.
     */
    public function domain(): string
    {
        return self::domainOf($this->email_address);
    }

    /**
     * Whether the send policy lets cc: reply to this address from here.
     */
    public function maySendTo(string $address): bool
    {
        $address = strtolower(trim($address));

        if (! str_contains($address, '@')) {
            return false;
        }

        return match ($this->send_policy) {
            SendPolicy::Off => false,
            SendPolicy::Whitelist => $this->listMatches($this->send_whitelist ?? [], $address),
            SendPolicy::Domain => self::domainOf($address) === $this->domain()
                && ! $this->listMatches($this->send_blacklist ?? [], $address),
            SendPolicy::Always => ! $this->listMatches($this->send_blacklist ?? [], $address),
        };
    }

    /**
     * An entry is a full address (jan@x.nl) or a domain (x.nl or @x.nl).
     *
     * @param  list<string>  $entries
     */
    private function listMatches(array $entries, string $address): bool
    {
        $domain = self::domainOf($address);

        foreach ($entries as $entry) {
            $entry = strtolower(trim($entry));

            if ($entry === $address || ltrim($entry, '@') === $domain) {
                return true;
            }
        }

        return false;
    }

    private static function domainOf(string $address): string
    {
        return strtolower(substr(strrchr($address, '@') ?: '', 1));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => MailboxProvider::class,
            'status' => MailboxStatus::class,
            'send_policy' => SendPolicy::class,
            'send_whitelist' => 'array',
            'send_blacklist' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'watch_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }
}
