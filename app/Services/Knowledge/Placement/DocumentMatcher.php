<?php

namespace App\Services\Knowledge\Placement;

use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Services\Knowledge\Extraction\ExtractedDocument;
use App\Services\Knowledge\Structure\SectionMatcher;
use App\Services\Knowledge\Structure\StructureBuilder;
use Illuminate\Support\Str;

/**
 * Finds the document a new file is a version of, before anything costs a
 * token: by the sections it shares word for word with a live document, and
 * by its file name once dates, version numbers and "final" are left out.
 */
class DocumentMatcher
{
    public function __construct(private readonly StructureBuilder $structure) {}

    public function match(DocumentVersion $version, ExtractedDocument $extracted): ?DocumentMatch
    {
        $hashes = collect($this->structure->build($extracted))
            ->pluck('contentHash')
            ->reject(fn (string $hash): bool => $hash === SectionMatcher::EMPTY_HASH)
            ->unique()
            ->values();

        $live = Document::query()->whereNotNull('current_version_id')->whereKeyNot($version->document_id);

        /** @var array<int, int> $shared document id => sections shared */
        $shared = $hashes->isEmpty() ? [] : DocumentSection::query()
            ->whereIn('document_version_id', (clone $live)->select('current_version_id'))
            ->whereIn('content_hash', $hashes->all())
            ->groupBy('document_id')
            ->selectRaw('document_id, count(distinct content_hash) as shared')
            ->pluck('shared', 'document_id')
            ->map(intval(...))
            ->all();

        $stem = self::stem($version->original_filename);
        $best = null;

        foreach ((clone $live)->with('currentVersion')->get() as $document) {
            $current = $document->currentVersion;

            if ($current === null) {
                continue;
            }

            $overlap = $hashes->isEmpty() ? 0.0 : ($shared[$document->id] ?? 0) / $hashes->count();
            $sameName = $stem !== '' && self::stem($current->original_filename) === $stem;

            $match = match (true) {
                $overlap >= (float) config('knowledge.placement.min_section_overlap') => new DocumentMatch($document, $overlap, (string) __(':percent% of its sections are the same', ['percent' => (int) round($overlap * 100)])),
                $sameName && ($overlap > 0 || $current->format() === $version->format()) => new DocumentMatch($document, max($overlap, 0.25), (string) __('same file name')),
                default => null,
            };

            if ($match !== null && ($best === null || $match->score > $best->score)) {
                $best = $match;
            }
        }

        return $best;
    }

    /**
     * "Verlofregeling_2027-v3 (definitief).pdf" and "verlofregeling 2026.docx"
     * are the same document: what is left of a name without its extension,
     * dates, years, version numbers and draft words.
     */
    public static function stem(string $filename): string
    {
        $name = str_replace(['_', '-', '.'], ' ', Str::lower(pathinfo($filename, PATHINFO_FILENAME)));

        $name = (string) preg_replace([
            '/\(\d+\)/u',
            '/\b\d{4}\s+\d{1,2}\s+\d{1,2}\b|\b\d{1,2}\s+\d{1,2}\s+\d{4}\b|\b\d{8}\b/u',
            '/\b(?:19|20)\d{2}\b/u',
            '/\b(?:v|versie|version|rev)\s*\d+(?:[._]\d+)*\b/u',
            '/\b(?:final|definitief|def|concept|draft|kopie|copy|nieuw|new|updated|bijgewerkt)\b/u',
        ], ' ', $name);

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
    }
}
