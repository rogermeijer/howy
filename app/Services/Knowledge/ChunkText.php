<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;

/**
 * The text that represents a chunk in the index: where it sits (document and
 * heading path), the context line, then the chunk itself. Embedding the path
 * along with the text is what makes a short chunk findable.
 */
class ChunkText
{
    public static function forEmbedding(KnowledgeChunk $chunk, string $documentTitle, string $headingPath): string
    {
        $location = trim($documentTitle.($headingPath !== '' ? ' › '.$headingPath : ''));

        return implode("\n", array_filter([
            $location,
            $chunk->context,
            '',
            $chunk->content,
        ], fn (?string $line): bool => $line !== null));
    }
}
