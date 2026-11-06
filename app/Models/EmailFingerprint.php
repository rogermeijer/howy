<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\FingerprintKind;
use App\Enums\FingerprintUse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The hash of a message or paragraph Howy has already read, and where.
 *
 * @property int $id
 * @property int $account_id
 * @property int $email_id
 * @property FingerprintKind $kind
 * @property string $hash
 * @property int $char_count
 * @property FingerprintUse $handled_as
 * @property int|null $document_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Email $email
 * @property-read DocumentVersion|null $documentVersion
 */
#[Fillable(['email_id', 'kind', 'hash', 'char_count', 'handled_as', 'document_version_id'])]
class EmailFingerprint extends Model
{
    use BelongsToAccount;

    /**
     * @return BelongsTo<Email, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FingerprintKind::class,
            'handled_as' => FingerprintUse::class,
        ];
    }
}
