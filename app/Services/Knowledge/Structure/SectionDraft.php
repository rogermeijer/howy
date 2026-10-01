<?php

namespace App\Services\Knowledge\Structure;

use App\Enums\SectionChange;
use App\Services\Knowledge\Extraction\Block;

/**
 * A section before it is stored: its heading, its place in the tree and the
 * blocks directly under it (not those of its subsections).
 */
final class SectionDraft
{
    public ?int $previousSectionId = null;

    public SectionChange $change = SectionChange::Added;

    /**
     * @param  list<Block>  $blocks
     */
    public function __construct(
        public readonly int $ordinal,
        public readonly int $level,
        public readonly ?string $heading,
        public readonly string $headingPath,
        public readonly ?int $parentOrdinal,
        public readonly array $blocks,
        public readonly ?int $pageFrom,
        public readonly ?int $pageTo,
        public readonly string $contentHash,
        public readonly int $tokenCount,
    ) {}

    public function body(): string
    {
        return implode("\n\n", array_map(fn (Block $block): string => $block->text, $this->blocks));
    }
}
