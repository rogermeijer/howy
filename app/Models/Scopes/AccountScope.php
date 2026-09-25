<?php

namespace App\Models\Scopes;

use App\Facades\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant model to the current account.
 *
 * Applied automatically by the BelongsToAccount trait, which carries the
 * #[ScopedBy] attribute, so models never register this themselves.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class AccountScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Tenancy::disabled()) {
            return;
        }

        // qualifyColumn() rather than a bare 'account_id': any join against another
        // tenant table would otherwise make the column ambiguous and fail in SQL.
        $builder->where($model->qualifyColumn('account_id'), Tenancy::id());
    }
}
