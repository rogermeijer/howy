<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Enums\ProcessingStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * An uploaded document in the knowledge base: a handbook, a policy, a manual.
 *
 * The document is the stable identity; its content lives in versions. Only the
 * current version is searched, older ones are kept for traceability.
 *
 * @property int $id
 * @property int $account_id
 * @property string $title
 * @property DocumentType $type
 * @property bool $is_core
 * @property Locale $language
 * @property Carbon|null $effective_date
 * @property int|null $current_version_id
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read DocumentVersion|null $currentVersion
 * @property-read User|null $uploadedBy
 * @property-read Collection<int, DocumentVersion> $versions
 * @property-read int|null $versions_count
 * @property-read Collection<int, KnowledgeTopicLink> $topicLinks
 */
#[Fillable(['title', 'type', 'is_core', 'language', 'effective_date', 'current_version_id', 'uploaded_by_user_id'])]
class Document extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DocumentVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('version_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return MorphMany<KnowledgeTopicLink, $this>
     */
    public function topicLinks(): MorphMany
    {
        return $this->morphMany(KnowledgeTopicLink::class, 'linkable');
    }

    /**
     * The document's status is its current version's status.
     */
    public function status(): ProcessingStatus
    {
        return $this->currentVersion->status ?? ProcessingStatus::Queued;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'is_core' => 'boolean',
            'language' => Locale::class,
            'effective_date' => 'date',
        ];
    }
}
