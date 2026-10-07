<?php

namespace App\Models;

use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A private beta invite code. Registration needs one.
 *
 * Platform data, never tenant-scoped: it is redeemed before the account it
 * creates exists. Hand them out with `php artisan vouchers:create`.
 *
 * @property int $id
 * @property string $code
 * @property string|null $note
 * @property int $max_uses
 * @property int $uses
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 */
#[Fillable(['code', 'note', 'max_uses', 'expires_at'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    /** Where a checked code waits between the code step and the registration form. */
    public const string SESSION_KEY = 'register.voucher';

    /** No 0/O or 1/I/L: codes get read out loud and typed over from a screenshot. */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * A fresh, unused code such as HOWY-7KQM-W2XP.
     */
    public static function generateCode(): string
    {
        do {
            $code = 'HOWY-'.self::randomBlock().'-'.self::randomBlock();
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * The code as stored, whatever case or stray spaces it was typed with.
     */
    public static function normalize(string $code): string
    {
        return Str::upper((string) preg_replace('/\s+/', '', $code));
    }

    /**
     * The voucher behind a typed code, if it can still let someone in.
     */
    public static function findRedeemable(string $code): ?self
    {
        $voucher = self::query()->where('code', self::normalize($code))->first();

        return $voucher?->isRedeemable() ? $voucher : null;
    }

    public function isRedeemable(): bool
    {
        return $this->uses < $this->max_uses
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'uses' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    private static function randomBlock(): string
    {
        $block = '';

        for ($i = 0; $i < 4; $i++) {
            $block .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $block;
    }
}
