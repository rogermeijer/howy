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
 * @property Locale $locale
 * @property Locale $knowledge_locale the language the knowledge base is built in
 * @property string $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 */
#[Fillable(['name', 'locale', 'timezone'])]
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'knowledge_locale' => Locale::class,
        ];
    }
}
