<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\AccountTopic;
use App\Enums\DocumentFormat;
use App\Enums\ProcessingStatus;
use App\Enums\SourceKind;
use App\Events\AccountChanged;
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
 * @property string|null $text_sha256
 * @property array{kind: string, document_id?: int|null, version_number?: int|null, score?: float|null, reason?: string|null}|null $placement
 * @property int|null $page_count
 * @property ProcessingStatus $status
 * @property string|null $error
 * @property string|null $extracted_path
 * @property int|null $uploaded_by_user_id
 * @property Carbon|null $processed_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Document $document
 * @property-read User|null $uploadedBy
 * @property-read User|null $approvedBy
 * @property-read Collection<int, DocumentSection> $sections
 * @property-read Collection<int, KnowledgeChunk> $chunks
 * @property-read Collection<int, KnowledgeFact> $facts
 * @property-read Collection<int, DocumentProcessingStep> $steps
 * @property-read Collection<int, DocumentSource> $sources
 * @property-read KnowledgeSummary|null $summary
 */
#[Fillable([
    'document_id', 'version_number', 'original_filename', 'mime_type', 'size_bytes',
    'disk', 'path', 'sha256', 'text_sha256', 'page_count', 'status', 'error', 'extracted_path', 'placement',
    'uploaded_by_user_id', 'processed_at', 'approved_at', 'approved_by_user_id',
])]
class DocumentVersion extends Model
{
    use BelongsToAccount;

    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    /**
     * A version that comes to wait for approval, or stops waiting, moves the
     * count in the top bar. The steps of the pipeline before it stay quiet.
     */
    protected static function booted(): void
    {
        static::saved(function (DocumentVersion $version): void {
            $waited = $version->wasRecentlyCreated ? null : $version->getOriginal('status');

            if ($version->wasChanged('approved_at')
                || ($version->status === ProcessingStatus::Review) !== ($waited === ProcessingStatus::Review)) {
                AccountChanged::dispatch($version->account_id, AccountTopic::Approvals);
            }
        });
    }

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
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
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
     * Every upload and every mail that brought this file.
     *
     * @return HasMany<DocumentSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(DocumentSource::class)->orderBy('id');
    }

    /**
     * Shared by mail rather than uploaded on purpose: its facts are proposed
     * like those of a mail, and count as supplementary.
     */
    public function isFromMail(): bool
    {
        $sources = $this->relationLoaded('sources') ? $this->sources : $this->sources()->get();

        return $sources->contains(fn (DocumentSource $source): bool => $source->kind === SourceKind::Mail);
    }

    /**
     * @return MorphOne<KnowledgeSummary, $this>
     */
    public function summary(): MorphOne
    {
        return $this->morphOne(KnowledgeSummary::class, 'summarizable');
    }

    /**
     * What kind of file it is, from the mime type it was stored with.
     */
    public function format(): DocumentFormat
    {
        return DocumentFormat::detect($this->original_filename, $this->mime_type) ?? DocumentFormat::Pdf;
    }

    public function isPdf(): bool
    {
        return $this->format() === DocumentFormat::Pdf;
    }

    /**
     * Approved by an administrator: its content may be searched.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Where a version ends up once it has been embedded but a later step gave
     * up: still searchable when it is published, still reviewable when not.
     */
    public function settledStatus(): ProcessingStatus
    {
        return $this->isApproved() ? ProcessingStatus::Searchable : ProcessingStatus::Review;
    }

    /**
     * The published version this one follows, if any. A version waiting for
     * review is compared with what is live, never with another draft.
     */
    public function previous(): ?self
    {
        return self::query()
            ->where('document_id', $this->document_id)
            ->where('version_number', '<', $this->version_number)
            ->whereNotNull('approved_at')
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * Swap in one go: this version's chunks become the ones that answer for
     * the document, every other version's stop.
     */
    public function makeCurrent(): void
    {
        $chunks = KnowledgeChunk::query()->where('document_id', $this->document_id)->where('source_type', 'document_version');

        (clone $chunks)->where('source_id', '!=', $this->id)->update(['is_current' => false]);
        (clone $chunks)->where('source_id', $this->id)->update(['is_current' => true]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProcessingStatus::class,
            'processed_at' => 'datetime',
            'approved_at' => 'datetime',
            'placement' => 'array',
        ];
    }
}
