<?php

namespace App\Services\Knowledge\Facts;

use App\Models\Document;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeFactSource;

/**
 * Where in a document a fact is stated, as a reviewer reads it: the passage
 * of its section, with the words that state it marked. Those are the quote
 * the fact was extracted with when there is one; else, for facts from before
 * quotes, the sentence that shares the most with the statement (names,
 * numbers), when that is clear enough; else nothing is marked.
 */
class FactPassage
{
    /**
     * @return array{sectionId: int|null, heading: string|null, versionNumber: int|null, before: string, quote: string|null, after: string, exact: bool, statement: string}|null
     */
    public function for(KnowledgeFact $fact, Document $document): ?array
    {
        $source = $fact->sources()
            ->with(['section.chunks', 'version'])
            ->where('document_id', $document->id)
            ->get()
            ->sortBy(fn (KnowledgeFactSource $source): int => $source->section_id === $fact->section_id ? 0 : 1)
            ->first();

        if ($source === null || $source->section === null) {
            return null;
        }

        $chunks = $source->section->chunks->sortBy('ordinal');
        $text = $source->chunk_id !== null
            ? (string) ($chunks->firstWhere('id', $source->chunk_id)->content ?? $chunks->pluck('content')->implode("\n\n"))
            : $chunks->pluck('content')->implode("\n\n");

        // The quote may sit in another chunk of the section than the one cited.
        if ($fact->quote !== null && self::locate($text, $fact->quote) === null) {
            $text = (string) ($chunks->first(fn (KnowledgeChunk $chunk): bool => self::locate($chunk->content, (string) $fact->quote) !== null)->content ?? $text);
        }

        $exact = $fact->quote !== null ? self::locate($text, $fact->quote) : null;
        $at = $exact ?? $this->likeliest($text, $fact->statement);

        return [
            'sectionId' => $source->section_id,
            'heading' => $source->section->heading_path,
            'versionNumber' => $source->version?->version_number,
            'before' => $at === null ? $text : substr($text, 0, $at[0]),
            'quote' => $at === null ? null : substr($text, $at[0], $at[1]),
            'after' => $at === null ? '' : substr($text, $at[0] + $at[1]),
            'exact' => $exact !== null,
            'statement' => $fact->statement,
        ];
    }

    /**
     * Where the quote stands in the text, whatever the line breaks and
     * spacing in between: its byte offset and length.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function locate(string $text, string $quote): ?array
    {
        $words = preg_split('/\s+/u', trim($quote), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === [] || preg_match('/'.implode('\s+', array_map(fn (string $word): string => preg_quote($word, '/'), $words)).'/iu', $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return [$match[0][1], strlen($match[0][0])];
    }

    /**
     * The sentence that shares the most telling words with the statement:
     * names, terms and numbers survive translation. Two at least, or none.
     *
     * @return array{0: int, 1: int}|null
     */
    private function likeliest(string $text, string $statement): ?array
    {
        $wanted = array_flip($this->words($statement));
        $best = null;
        $score = 1;

        preg_match_all('/[^.!?\n|]+[.!?]?/u', $text, $sentences, PREG_OFFSET_CAPTURE);

        foreach ($sentences[0] as [$sentence, $offset]) {
            $shared = count(array_intersect_key(array_flip($this->words($sentence)), $wanted));

            if ($shared > $score) {
                $score = $shared;
                $trimmed = ltrim($sentence);
                $best = [$offset + strlen($sentence) - strlen($trimmed), strlen(rtrim($trimmed))];
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        preg_match_all('/\p{Lu}[\p{L}\-]+|\d[\d.,:]*|\p{L}{6,}/u', $text, $matches);

        return array_values(array_unique(array_map(fn (string $word): string => mb_strtolower(rtrim($word, '.,:')), $matches[0])));
    }
}
