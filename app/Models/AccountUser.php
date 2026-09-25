<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Membership of a user in an account.
 *
 * is_admin here overrides every permission *within this account*, and is separate
 * from users.is_admin, which is platform-wide. Read both through the helpers on
 * User rather than touching the columns directly.
 *
 * @property int $id
 * @property int $account_id
 * @property int $user_id
 * @property Role|null $role
 * @property bool $is_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccountUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'account_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_admin' => 'boolean',
        ];
    }
}
