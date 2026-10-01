<?php

namespace App\Services\Knowledge\Structure;

use App\Enums\SectionChange;
use App\Models\DocumentSection;
use Illuminate\Support\Collection;

/**
 * Pairs the sections of a new version with those of the previous one, so what
 * did not change keeps its summary, facts and topic links, and only the rest
 * is processed again.
 *
 * In order of confidence:
 * 1. same heading path and same body          → unchanged
 * 2. same non-empty body under another heading → unchanged (moved or renamed)
 * 3. same heading path, different body         → changed
 * 4. similar heading (Levenshtein ≥ 0.8) or
 *    similar body (word-shingle Jaccard ≥ 0.6) → changed
 * 5. anything else                             → added
 *
 * Every previous section is matched at most once.
 */
class SectionMatcher
{
    private const string EMPTY_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /**
     * @param  list<SectionDraft>  $drafts
     * @param  Collection<int, DocumentSection>  $previous
     */
    public function match(array $drafts, Collection $previous): void
    {
        /** @var array<int, DocumentSection> $available */
        $available = $previous->keyBy('id')->all();

        $passes = [
            fn (SectionDraft $draft, DocumentSection $old): bool => $this->samePath($draft, $old) && $draft->contentHash === $old->content_hash,
            fn (SectionDraft $draft, DocumentSection $old): bool => $draft->contentHash !== self::EMPTY_HASH && $draft->contentHash === $old->content_hash,
            fn (SectionDraft $draft, DocumentSection $old): bool => $this->samePath($draft, $old),
            fn (SectionDraft $draft, DocumentSection $old): bool => $this->similar($draft, $old),
        ];

        foreach ($passes as $pass) {
            foreach ($drafts as $draft) {
                if ($draft->previousSectionId !== null) {
                    continue;
                }

                foreach ($available as $id => $old) {
                    if ($pass($draft, $old)) {
                        $draft->previousSectionId = $id;
                        $draft->change = $draft->contentHash === $old->content_hash ? SectionChange::Unchanged : SectionChange::Changed;
                        unset($available[$id]);

                        break;
                    }
                }
            }
        }
    }

    private function samePath(SectionDraft $draft, DocumentSection $old): bool
    {
        return $this->normalise($draft->headingPath) === $this->normalise($old->heading_path);
    }

    private function similar(SectionDraft $draft, DocumentSection $old): bool
    {
        $a = $this->normalise($draft->heading ?? '');
        $b = $this->normalise($old->heading ?? '');

        if ($a !== '' && $b !== '') {
            $longest = max(strlen($a), strlen($b));

            if ($longest <= 255 && 1 - levenshtein($a, $b) / $longest >= 0.8) {
                return true;
            }
        }

        $oldBody = $old->relationLoaded('chunks')
            ? $old->chunks->pluck('content')->implode("\n\n")
            : '';

        return $oldBody !== '' && $this->jaccard($draft->body(), $oldBody) >= 0.6;
    }

    private function jaccard(string $a, string $b): float
    {
        $shingles = function (string $text): array {
            $words = preg_split('/\W+/u', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
            $set = [];

            for ($i = 0; $i < count($words) - 2; $i++) {
                $set[$words[$i].' '.$words[$i + 1].' '.$words[$i + 2]] = true;
            }

            return $set;
        };

        $x = $shingles($a);
        $y = $shingles($b);

        if ($x === [] || $y === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($x, $y));

        return $intersection / (count($x) + count($y) - $intersection);
    }

    private function normalise(string $text): string
    {
        // Numbering is noise: "3.2 Ziekmelding" and "4.1 Ziekmelding" are the same section.
        $text = (string) preg_replace('/(^|›)\s*\d+(?:\.\d+)*\.?\s+/u', '$1', mb_strtolower($text));

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
