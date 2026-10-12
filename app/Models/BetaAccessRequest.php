<?php

namespace App\Models;

use Database\Factories\BetaAccessRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Someone asking for a private beta invite, and the single-use code that lets
 * them in.
 *
 * Platform data, never tenant-scoped: whoever asks has no user or account yet,
 * and the code is redeemed before the account it creates exists. The code is
 * minted the moment the request comes in; nothing is sent until an admin
 * invites them from /admin/beta.
 *
 * @property int $id
 * @property string $email
 * @property string|null $locale
 * @property string $code
 * @property Carbon|null $invited_at
 * @property Carbon|null $redeemed_at
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['email', 'locale'])]
class BetaAccessRequest extends Model
{
    /** @use HasFactory<BetaAccessRequestFactory> */
    use HasFactory;

    /** Where a checked code waits between the code step and the registration form. */
    public const string SESSION_KEY = 'register.code';

    /** No 0/O or 1/I/L: codes get read out loud and typed over from a screenshot. */
    private const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Put an address on the list, with its code. Asking twice returns the
     * request that is already there.
     */
    public static function submit(string $email, ?string $locale): self
    {
        return self::query()->firstOrCreate(
            ['email' => Str::lower(trim($email))],
            ['locale' => $locale],
        );
    }

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
     * The request behind a typed code, if that code can still let someone in.
     */
    public static function findRedeemable(string $code): ?self
    {
        $request = self::query()->where('code', self::normalize($code))->first();

        return $request?->isRedeemable() ? $request : null;
    }

    public function isRedeemable(): bool
    {
        return $this->redeemed_at === null;
    }

    /**
     * @return 'pending'|'invited'|'registered'
     */
    public function status(): string
    {
        return match (true) {
            $this->redeemed_at !== null => 'registered',
            $this->invited_at !== null => 'invited',
            default => 'pending',
        };
    }

    /**
     * The user who registered with this code.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->code ??= self::generateCode();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'redeemed_at' => 'datetime',
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
