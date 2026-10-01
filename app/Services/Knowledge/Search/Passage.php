<?php

namespace App\Services\Knowledge\Search;

/**
 * A piece of a document to answer from, with everything needed to cite it.
 */
final readonly class Passage
{
    /**
     * @param  list<int>  $chunkIds
     * @param  array{vector: int|null, text: int|null}  $ranks  best ranks among the chunks, for debugging
     */
    public function __construct(
        public int $documentId,
        public int $versionId,
        public int $versionNumber,
        public string $documentTitle,
        public ?int $sectionId,
        public string $headingPath,
        public ?int $pageFrom,
        public ?int $pageTo,
        public string $text,
        public float $score,
        public int $tokenCount,
        public array $chunkIds,
        public array $ranks,
        public bool $wholeSection,
    ) {}

    /**
     * "Personeelshandboek v2 · Verlof › Vakantie · p. 4"
     */
    public function citation(): string
    {
        $pages = match (true) {
            $this->pageFrom === null => null,
            $this->pageFrom === $this->pageTo, $this->pageTo === null => "p. {$this->pageFrom}",
            default => "p. {$this->pageFrom}–{$this->pageTo}",
        };

        return implode(' · ', array_filter(["{$this->documentTitle} v{$this->versionNumber}", $this->headingPath, $pages]));
    }
}
