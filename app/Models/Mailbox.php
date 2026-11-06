<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Enums\SenderAccess;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A shared mailbox (support@, kennis@…) the account has connected.
 *
 * It belongs to the account, not to the person who connected it:
 * connected_by_user_id only records who authorised it with Google.
 *
 * What Howy may do with a sender's mail (SenderAccess) comes from the first of:
 * the address among its senders, its domain among its senders, and
 * domain_access for the mailbox's own domain (not on a public one such as
 * gmail.com). Everyone else is ignored.
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
 * @property Carbon|null $import_offer_answered_at
 * @property SenderAccess $domain_access
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read User|null $connectedBy
 * @property-read Collection<int, Email> $emails
 * @property-read int|null $emails_count
 * @property-read Collection<int, MailboxSender> $senders
 * @property-read int|null $senders_count
 */
#[Fillable([
    'connected_by_user_id', 'provider', 'email_address', 'provider_user_id',
    'access_token', 'refresh_token', 'token_expires_at', 'status', 'history_id',
    'watch_expires_at', 'last_synced_at', 'last_message_at', 'last_error',
    'import_batch_id', 'import_offer_answered_at', 'domain_access',
])]
#[Hidden(['access_token', 'refresh_token'])]
class Mailbox extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults, so a new mailbox knows its policy before it
     * is read back.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'domain_access' => 'learn_answer',
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

    /**
     * The addresses and domains listed on this mailbox, each with what Howy
     * may do with their mail.
     *
     * @return HasMany<MailboxSender, $this>
     */
    public function senders(): HasMany
    {
        return $this->hasMany(MailboxSender::class);
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
     * Whether anyone can have an address on the mailbox's domain (gmail.com…):
     * then "the same domain" means everyone, so domain_access does not count
     * and its senders are listed one by one.
     */
    public function isOnPublicDomain(): bool
    {
        return self::isPublicDomain($this->domain());
    }

    public static function isPublicDomain(string $domain): bool
    {
        /** @var list<string> $public */
        $public = config('mailboxes.public_domains', []);

        return in_array(strtolower($domain), $public, true);
    }

    /**
     * What Howy may do with mail from this address: the address on the list,
     * else its domain on the list, else the mailbox's own domain; anyone else
     * is ignored. An address beats its domain, so one address inside an
     * organisation can be ignored while the rest is read.
     */
    public function access(string $address): SenderAccess
    {
        $address = strtolower(trim($address));

        if (! str_contains($address, '@')) {
            return SenderAccess::Ignore;
        }

        $domain = self::domainOf($address);
        $listed = $this->senders->firstWhere('entry', $address) ?? $this->senders->firstWhere('entry', $domain);

        if ($listed !== null) {
            return $listed->access;
        }

        return $domain === $this->domain() && ! $this->isOnPublicDomain()
            ? $this->domain_access
            : SenderAccess::Ignore;
    }

    /**
     * Whether Howy reads mail from this address at all.
     */
    public function accepts(string $address): bool
    {
        return $this->access($address) !== SenderAccess::Ignore;
    }

    /**
     * Whether Howy may write to this address from here: reply to it, or copy
     * it in on a reply.
     */
    public function maySendTo(string $address): bool
    {
        return $this->access($address)->answers();
    }

    /**
     * List exactly these senders. One already listed keeps its row, with the
     * access given here; one not given goes; a new one is added, in the order
     * given, after the rest.
     *
     * @param  list<array{entry: string, access: SenderAccess|string}>  $senders
     */
    public function syncSenders(array $senders): void
    {
        DB::transaction(function () use ($senders): void {
            $listed = $this->senders()->get()->keyBy('entry');

            $this->senders()->whereNotIn('entry', array_column($senders, 'entry'))->delete();

            foreach ($senders as $sender) {
                ($listed->get($sender['entry']) ?? $this->senders()->make(['entry' => $sender['entry']]))
                    ->fill(['access' => $sender['access']])
                    ->save();
            }
        });

        $this->unsetRelation('senders');
    }

    /**
     * Once, for a mailbox that never imported anything: offer to import older
     * mail. Turned down, or an import made, it is not offered again.
     */
    public function offersImport(): bool
    {
        return $this->isActive() && $this->import_batch_id === null && $this->import_offer_answered_at === null;
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
            'domain_access' => SenderAccess::class,
            'import_offer_answered_at' => 'datetime',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'watch_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }
}
