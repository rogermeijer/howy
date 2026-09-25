<?php

namespace App\Concerns;

use App\Exceptions\CrossTenantWriteException;
use App\Exceptions\TenantContextMissingException;
use App\Facades\Tenancy;
use App\Models\Account;
use App\Models\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes a model belong to exactly one account.
 *
 * Adding `use BelongsToAccount;` is the whole job: the #[ScopedBy] attribute is
 * resolved from directly-used traits, so the global scope comes along with it, and
 * the boot hooks below fill and defend account_id.
 *
 * IMPORTANT: the trait must sit on the model class itself. Nesting it inside
 * another trait silently loses the global scope, because ReflectionClass::getTraits()
 * only reports directly-used traits. TenantSchemaGuardTest enforces this.
 *
 * @property int $account_id
 * @property-read Account $account
 *
 * @mixin Model
 */
#[ScopedBy(AccountScope::class)]
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::creating(function (Model $model): void {
            $given = $model->getAttribute('account_id');

            if (Tenancy::disabled()) {
                // Scoping is off, so there is no context to fill from. Requiring an
                // explicit account_id here stops withoutTenancy() from quietly
                // creating orphaned rows.
                if ($given === null) {
                    throw TenantContextMissingException::forCreate($model::class);
                }

                return;
            }

            if ($given === null) {
                $model->setAttribute('account_id', Tenancy::id());

                return;
            }

            if ((int) $given !== Tenancy::id()) {
                throw CrossTenantWriteException::forCreate($model::class, (int) $given, Tenancy::id());
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('account_id')) {
                throw CrossTenantWriteException::forMove($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
