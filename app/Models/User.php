<?php

namespace App\Models;

use App\Enums\Locale;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property int|null $active_account_id
 * @property Locale|null $locale
 * @property string|null $timezone
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Account> $accounts
 * @property-read Account|null $activeAccount
 */
#[Fillable(['name', 'email', 'password', 'locale', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The accounts this user is a member of.
     *
     * @return BelongsToMany<Account, $this, AccountUser>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)
            ->using(AccountUser::class)
            ->withPivot(['role', 'is_admin'])
            ->withTimestamps();
    }

    /**
     * The account this user was last active on.
     *
     * This is a pointer, not the truth: it can go stale when a membership is
     * removed, which SetTenantContext heals. Never trust it without checking
     * membership first.
     *
     * @return BelongsTo<Account, $this>
     */
    public function activeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'active_account_id');
    }

    /**
     * A platform-wide administrator, which overrides every permission everywhere.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_admin;
    }

    public function belongsToAccount(Account|int $account): bool
    {
        return $this->accounts()
            ->whereKey($account instanceof Account ? $account->getKey() : $account)
            ->exists();
    }

    /**
     * An administrator of one account, which overrides every permission inside it.
     */
    public function isAdminOf(Account|int $account): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membership = $this->accounts()
            ->whereKey($account instanceof Account ? $account->getKey() : $account)
            ->first();

        return (bool) $membership?->getAttribute('pivot')->is_admin;
    }

    /**
     * The locale to render for this user.
     *
     * A null column means "follow the account", so the fallback needs no separate
     * sentinel value.
     */
    public function resolvedLocale(): string
    {
        return $this->locale->value
            ?? $this->activeAccount->locale->value
            ?? config()->string('app.locale');
    }

    public function resolvedTimezone(): string
    {
        return $this->timezone
            ?? $this->activeAccount->timezone
            ?? config()->string('app.timezone');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'locale' => Locale::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
