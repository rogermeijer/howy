<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ProcessingStatus;
use Database\Factories\DocumentVersionFactory;
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
 * One uploaded file of a document. Everything derived from it — sections,
 * chunks, facts — points back here, so every answer can name the version,
 * section and page it came from.
 *
 * @property int $id
 * @property int $account_id
 * @property int $document_id
 * @property int $version_number
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $disk
 * @property string $path
 * @property string $sha256
 * @property int|null $page_count
 * @property ProcessingStatus $status
 * @property string|null $error
 * @property string|null $extracted_path
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Document $document
 * @property-read User|null $uploadedBy
 * @property-read Collection<int, DocumentSection> $sections
 * @property-read Collection<int, KnowledgeChunk> $chunks
 * @property-read Collection<int, KnowledgeFact> $facts
 * @property-read Collection<int, DocumentProcessingStep> $steps
 * @property-read KnowledgeSummary|null $summary
 */
#[Fillable([
    'document_id', 'version_number', 'original_filename', 'mime_type', 'size_bytes',
    'disk', 'path', 'sha256', 'page_count', 'status', 'error', 'extracted_path',
    'uploaded_by_user_id', 'processed_at',
])]
class DocumentVersion extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    public const string PDF = 'application/pdf';

    public const string DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<DocumentSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(DocumentSection::class)->orderBy('ordinal');
    }

    /**
     * @return MorphMany<KnowledgeChunk, $this>
     */
    public function chunks(): MorphMany
    {
        return $this->morphMany(KnowledgeChunk::class, 'source')->orderBy('ordinal');
    }

    /**
     * @return MorphMany<KnowledgeFact, $this>
     */
    public function facts(): MorphMany
    {
        return $this->morphMany(KnowledgeFact::class, 'source');
    }

    /**
     * @return HasMany<DocumentProcessingStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(DocumentProcessingStep::class)->orderBy('id');
    }

    /**
     * @return MorphOne<KnowledgeSummary, $this>
     */
    public function summary(): MorphOne
    {
        return $this->morphOne(KnowledgeSummary::class, 'summarizable');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === self::PDF;
    }

    /**
     * The version this one replaced, if any.
     */
    public function previous(): ?self
    {
        return self::query()
            ->where('document_id', $this->document_id)
            ->where('version_number', '<', $this->version_number)
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProcessingStatus::class,
            'processed_at' => 'datetime',
        ];
    }
}
