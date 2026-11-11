<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\MailboxProvider;
use App\Enums\MailboxStatus;
use App\Enums\SenderAccess;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
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
 * What Howy may do with a sender's mail (SenderAccess) comes from its rules:
 * the sender's own rule as a colleague, else the rule for their domain (the
 * mailbox's own domain among them, except on a public one such as gmail.com).
 * Everyone else is ignored. MailboxRuleEditor writes them.
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read User|null $connectedBy
 * @property-read Collection<int, Email> $emails
 * @property-read int|null $emails_count
 * @property-read Collection<int, MailboxRule> $rules
 * @property-read int|null $rules_count
 */
#[Fillable([
    'connected_by_user_id', 'provider', 'email_address', 'provider_user_id',
    'access_token', 'refresh_token', 'token_expires_at', 'status', 'history_id',
    'watch_expires_at', 'last_synced_at', 'last_message_at', 'last_error',
    'import_batch_id', 'import_offer_answered_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class Mailbox extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

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
     * What Howy may do with mail here, per colleague and per domain.
     *
     * @return HasMany<MailboxRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(MailboxRule::class);
    }

    public function isActive(): bool
    {
        return $this->status === MailboxStatus::Active;
    }

    /**
     * The mailboxes an account has: connected, or waiting to be reconnected.
     * A disconnected one is only kept for the mail it brought in.
     *
     * @param  Builder<Mailbox>  $query
     * @return Builder<Mailbox>
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', '!=', MailboxStatus::Disconnected);
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
     * then "the same domain" means everyone, so there is no rule for it and
     * its senders are listed one by one.
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
     * The rule that decides for this address: the one for its colleague, else
     * the one for its domain. A colleague's rule beats their domain's, so one
     * person inside an organisation can be ignored while the rest is read.
     */
    public function ruleFor(string $address): ?MailboxRule
    {
        $address = strtolower(trim($address));

        if (! str_contains($address, '@')) {
            return null;
        }

        $this->loadMissing('rules.colleague');
        $domain = self::domainOf($address);

        return $this->rules->first(fn (MailboxRule $rule): bool => $rule->colleague?->email === $address)
            ?? $this->rules->first(fn (MailboxRule $rule): bool => $rule->domain === $domain);
    }

    /**
     * What Howy may do with mail from this address; anyone no rule covers is
     * ignored.
     */
    public function access(string $address): SenderAccess
    {
        return $this->ruleFor($address)->access ?? SenderAccess::Ignore;
    }

    /**
     * The rule for the mailbox's own domain, for its colleagues. None on a
     * public domain.
     */
    public function ownDomainRule(): ?MailboxRule
    {
        if ($this->isOnPublicDomain()) {
            return null;
        }

        $this->loadMissing('rules');

        return $this->rules->first(fn (MailboxRule $rule): bool => $rule->domain === $this->domain());
    }

    /**
     * What Howy may do with mail from the mailbox's own domain: nothing on a
     * public domain, or once its rule is gone.
     */
    public function domainAccess(): SenderAccess
    {
        return $this->ownDomainRule()->access ?? SenderAccess::Ignore;
    }

    /**
     * Whether mail from this address makes its sender a colleague: someone
     * Howy reads, with a rule of their own or let in by a domain rule that
     * creates colleagues.
     */
    public function createsColleague(string $address): bool
    {
        $rule = $this->ruleFor($address);

        return $rule !== null
            && $rule->access !== SenderAccess::Ignore
            && ($rule->domain === null || $rule->creates_colleagues);
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
     * Once, for a mailbox that never imported anything: offer to import older
     * mail. Turned down, or an import made, it is not offered again.
     */
    public function offersImport(): bool
    {
        return $this->isActive() && $this->import_batch_id === null && $this->import_offer_answered_at === null;
    }

    public static function domainOf(string $address): string
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
