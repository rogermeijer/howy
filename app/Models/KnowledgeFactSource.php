<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\FactSourceKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One place a fact is stated: a section of a document, or a mail. A claim
 * found again elsewhere is one more source of the same fact. Removed with its
 * document or mail, the row stays as a named, removed source when the fact is
 * kept.
 *
 * @property int $id
 * @property int $account_id
 * @property int $knowledge_fact_id
 * @property FactSourceKind $kind
 * @property int|null $document_id
 * @property int|null $document_version_id
 * @property int|null $section_id
 * @property int|null $chunk_id
 * @property int|null $email_id
 * @property int|null $page_from
 * @property int|null $page_to
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read KnowledgeFact $fact
 * @property-read Document|null $document
 * @property-read DocumentVersion|null $version
 * @property-read DocumentSection|null $section
 * @property-read Email|null $email
 */
#[Fillable(['knowledge_fact_id', 'kind', 'document_id', 'document_version_id', 'section_id', 'chunk_id', 'email_id', 'page_from', 'page_to', 'title'])]
class KnowledgeFactSource extends Model
{
    use BelongsToAccount;

    /**
     * Its document or mail is still there.
     */
    public function isLive(): bool
    {
        return $this->kind === FactSourceKind::Mail ? $this->email_id !== null : $this->document_id !== null;
    }

    /**
     * @return BelongsTo<KnowledgeFact, $this>
     */
    public function fact(): BelongsTo
    {
        return $this->belongsTo(KnowledgeFact::class, 'knowledge_fact_id');
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
            'kind' => FactSourceKind::class,
        ];
    }
}
