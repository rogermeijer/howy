<?php

namespace App\Services\Knowledge\Facts;

use App\Enums\FactSourceKind;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;

/**
 * Every place a fact is stated, as the pages show it: the one it is cited
 * from first, then the others; a removed document by the name it had.
 *
 * Expects the fact's sources loaded with their version and section.
 */
class FactSourcePresenter
{
    /**
     * @return list<array{kind: string, title: string, documentId: int|null, versionNumber: int|null, sectionId: int|null, headingPath: string|null, pageFrom: int|null, emailId: int|null, removed: bool}>
     */
    public function present(KnowledgeFact $fact): array
    {
        return array_values($fact->sources
            ->sortBy(fn (KnowledgeFactSource $source): array => [$this->isPrimary($fact, $source) ? 0 : 1, $source->id])
            ->map(fn (KnowledgeFactSource $source): array => [
                'kind' => $source->kind->value,
                'title' => $source->title,
                'documentId' => $source->document_id,
                'versionNumber' => $source->version?->version_number,
                'sectionId' => $source->section_id,
                'headingPath' => $source->section?->heading_path,
                'pageFrom' => $source->page_from,
                'emailId' => $source->email_id,
                'removed' => ! $source->isLive(),
            ])
            ->all());
    }

    private function isPrimary(KnowledgeFact $fact, KnowledgeFactSource $source): bool
    {
        return $source->kind === FactSourceKind::Mail
            ? $fact->source_type === 'email' && $source->email_id === $fact->source_id
            : $source->section_id !== null && $source->section_id === $fact->section_id;
    }
}
