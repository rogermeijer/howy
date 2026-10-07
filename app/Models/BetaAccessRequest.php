<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Someone asking for a private beta invite.
 *
 * Stored on its own, not linked to a user or account: whoever asks has neither
 * yet. List them with `php artisan beta-access:list`.
 *
 * @property int $id
 * @property string $email
 * @property string|null $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'locale'])]
class BetaAccessRequest extends Model
{
    //
}
