<?php

namespace App\Services\Knowledge;

use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Takes in an upload as a new document or a new version, through the same
 * intake a mail attachment uses. An identical file (same sha256) is refused
 * within the account, so a document is only ever processed once.
 */
class DocumentUploader
{
    public function __construct(private readonly DocumentIntake $intake) {}

    /**
     * @param  array{title?: string|null, type: string|DocumentType, is_core: bool, language: string|Locale, effective_date?: string|null}  $attributes
     */
    public function create(UploadedFile $file, array $attributes, User $user): Document
    {
        return $this->intake->newDocument($this->path($file), $file->getClientOriginalName(), IntakeSource::upload($user), $attributes)->document;
    }

    public function addVersion(Document $document, UploadedFile $file, User $user): DocumentVersion
    {
        return $this->intake->newVersion($document, $this->path($file), $file->getClientOriginalName(), IntakeSource::upload($user));
    }

    /**
     * @return array{kept: int, removed: int}
     */
    public function delete(Document $document, bool $keepFacts): array
    {
        return $this->intake->delete($document, $keepFacts);
    }

    private function path(UploadedFile $file): string
    {
        return $file->getRealPath() ?: throw new \RuntimeException('The upload could not be read.');
    }
}
