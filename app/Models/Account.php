<?php

namespace App\Models;

use App\Enums\Locale;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A tenant. Every row of business data belongs to exactly one of these.
 *
 * Account itself is never tenant-scoped — it *is* the tenant — so it must never
 * use the BelongsToAccount trait.
 *
 * @property int $id
 * @property string $name
 * @property string|null $domain the domain the company mails from, never a public one
 * @property Locale $locale
 * @property Locale $knowledge_locale the language the knowledge base is built in
 * @property string $timezone
 * @property Carbon|null $onboarded_at when an administrator finished the setup wizard
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, User> $admins
 */
#[Fillable(['name', 'domain', 'locale', 'timezone'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * The knowledge base starts out in the account's own language, and from
     * then on goes its own way: knowledge_locale is not fillable, so no
     * account form can change it, only its own setting.
     */
    protected static function booted(): void
    {
        static::creating(function (Account $account): void {
            $account->knowledge_locale ??= $account->locale;
        });
    }

    /**
     * @return BelongsToMany<User, $this, AccountUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(AccountUser::class)
            ->withPivot(['role', 'is_admin'])
            ->withTimestamps();
    }

    /**
     * The members who administer this account (account_user.is_admin). A
     * platform super-admin is not one unless made admin here.
     *
     * @return BelongsToMany<User, $this, AccountUser>
     */
    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('is_admin', true);
    }

    /**
     * Whether an administrator went through the setup wizard. Until then the
     * account's administrators are sent there before anything else.
     */
    public function isSetUp(): bool
    {
        return $this->onboarded_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'knowledge_locale' => Locale::class,
            'onboarded_at' => 'datetime',
        ];
    }
}
