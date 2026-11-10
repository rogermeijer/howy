<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\SourceKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time a file reached the knowledge base: uploaded by someone, or shared
 * in a mail. The same file sent again is one more source, not one more
 * version.
 *
 * @property int $id
 * @property int $account_id
 * @property int $document_id
 * @property int $document_version_id
 * @property SourceKind $kind
 * @property int|null $user_id
 * @property int|null $email_id
 * @property string $filename
 * @property string|null $context
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Document $document
 * @property-read DocumentVersion $version
 * @property-read User|null $user
 * @property-read Email|null $email
 */
#[Fillable(['document_id', 'document_version_id', 'kind', 'user_id', 'email_id', 'filename', 'context'])]
class DocumentSource extends Model
{
    use BelongsToAccount;

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Email, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
        ];
    }
}
