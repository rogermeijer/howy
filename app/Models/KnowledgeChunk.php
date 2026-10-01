<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ChunkKind;
use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The unit of retrieval: a piece of a section, with a short context line that
 * says where it comes from, an embedding and a generated tsvector.
 *
 * @property int $id
 * @property int $account_id
 * @property string $source_type
 * @property int $source_id
 * @property int|null $document_id
 * @property int|null $section_id
 * @property int $ordinal
 * @property ChunkKind $kind
 * @property string $content
 * @property string|null $context
 * @property int|null $page_from
 * @property int|null $page_to
 * @property int $token_count
 * @property string $content_hash
 * @property list<float>|null $embedding
 * @property string|null $embedding_model
 * @property bool $is_current
 * @property string $search_config
 * @property-read string|null $search_vector
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read DocumentVersion|Email $source
 * @property-read Document|null $document
 * @property-read DocumentSection|null $section
 */
#[Fillable([
    'source_type', 'source_id', 'document_id', 'section_id', 'ordinal', 'kind', 'content',
    'context', 'page_from', 'page_to', 'token_count', 'content_hash', 'embedding',
    'embedding_model', 'is_current', 'search_config',
])]
#[Hidden(['embedding', 'search_vector'])]
class KnowledgeChunk extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<KnowledgeChunkFactory> */
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
     * @return BelongsTo<DocumentSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(DocumentSection::class, 'section_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ChunkKind::class,
            'embedding' => AsVector::class,
            'is_current' => 'boolean',
        ];
    }
}
