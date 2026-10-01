<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\SectionChange;
use Database\Factories\DocumentSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * A heading and the text under it, in a tree (chapter › paragraph). Sections are
 * matched across versions so unchanged ones keep their derived data.
 *
 * @property int $id
 * @property int $account_id
 * @property int $document_id
 * @property int $document_version_id
 * @property int|null $parent_id
 * @property int $level
 * @property int $ordinal
 * @property string|null $heading
 * @property string $heading_path
 * @property int|null $page_from
 * @property int|null $page_to
 * @property string $content_hash
 * @property int $token_count
 * @property int|null $previous_section_id
 * @property SectionChange $change_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Document $document
 * @property-read DocumentVersion $version
 * @property-read DocumentSection|null $parent
 * @property-read Collection<int, DocumentSection> $children
 * @property-read DocumentSection|null $previousSection
 * @property-read Collection<int, KnowledgeChunk> $chunks
 * @property-read Collection<int, KnowledgeFact> $facts
 * @property-read KnowledgeSummary|null $summary
 * @property-read Collection<int, KnowledgeTopicLink> $topicLinks
 */
#[Fillable([
    'document_id', 'document_version_id', 'parent_id', 'level', 'ordinal', 'heading',
    'heading_path', 'page_from', 'page_to', 'content_hash', 'token_count',
    'previous_section_id', 'change_type',
])]
class DocumentSection extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<DocumentSectionFactory> */
    use HasFactory;

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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<DocumentSection, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordinal');
    }

    /**
     * @return BelongsTo<DocumentSection, $this>
     */
    public function previousSection(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_section_id');
    }

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'section_id')->orderBy('ordinal');
    }

    /**
     * @return HasMany<KnowledgeFact, $this>
     */
    public function facts(): HasMany
    {
        return $this->hasMany(KnowledgeFact::class, 'section_id');
    }

    /**
     * @return MorphOne<KnowledgeSummary, $this>
     */
    public function summary(): MorphOne
    {
        return $this->morphOne(KnowledgeSummary::class, 'summarizable');
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
            'change_type' => SectionChange::class,
        ];
    }
}
