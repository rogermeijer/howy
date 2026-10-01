<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\FactStatus;
use Database\Factories\KnowledgeFactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An atomic claim taken from a core document ("employees get 25 days of leave").
 * Incoming mails will be checked against these for conflicts.
 *
 * @property int $id
 * @property int $account_id
 * @property string $source_type
 * @property int $source_id
 * @property int|null $document_id
 * @property int|null $document_version_id
 * @property int|null $section_id
 * @property int|null $chunk_id
 * @property string $statement
 * @property string|null $subject
 * @property FactStatus $status
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property int|null $superseded_by_id
 * @property float|null $confidence
 * @property int|null $page_from
 * @property int|null $page_to
 * @property string $content_hash
 * @property list<float>|null $embedding
 * @property string|null $embedding_model
 * @property string $search_config
 * @property-read string|null $search_vector
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read DocumentVersion|Email $source
 * @property-read Document|null $document
 * @property-read DocumentVersion|null $version
 * @property-read DocumentSection|null $section
 * @property-read KnowledgeChunk|null $chunk
 * @property-read KnowledgeFact|null $supersededBy
 * @property-read Collection<int, KnowledgeTopicLink> $topicLinks
 */
#[Fillable([
    'source_type', 'source_id', 'document_id', 'document_version_id', 'section_id', 'chunk_id',
    'statement', 'subject', 'status', 'valid_from', 'valid_until', 'superseded_by_id',
    'confidence', 'page_from', 'page_to', 'content_hash', 'embedding', 'embedding_model',
    'search_config',
])]
#[Hidden(['embedding', 'search_vector'])]
class KnowledgeFact extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<KnowledgeFactFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

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
     * @return BelongsTo<DocumentSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(DocumentSection::class, 'section_id');
    }

    /**
     * @return BelongsTo<KnowledgeChunk, $this>
     */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'chunk_id');
    }

    /**
     * @return BelongsTo<KnowledgeFact, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * @return MorphMany<KnowledgeTopicLink, $this>
     */
    public function topicLinks(): MorphMany
    {
        return $this->morphMany(KnowledgeTopicLink::class, 'linkable');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FactStatus::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
            'confidence' => 'float',
            'embedding' => AsVector::class,
        ];
    }
}
