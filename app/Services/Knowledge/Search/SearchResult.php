<?php

namespace App\Services\Knowledge\Search;

final readonly class SearchResult
{
    /**
     * @param  list<Passage>  $passages
     * @param  list<FactHit>  $facts
     */
    public function __construct(
        public array $passages,
        public array $facts,
        public int $tokenCount,
        public bool $usedVectors,
    ) {}

    /**
     * The passages as prompt context, each with its source.
     */
    public function toPromptContext(): string
    {
        return implode("\n\n", array_map(
            fn (Passage $passage): string => "<source cite=\"{$passage->citation()}\">\n{$passage->text}\n</source>",
            $this->passages,
        ));
    }
}
