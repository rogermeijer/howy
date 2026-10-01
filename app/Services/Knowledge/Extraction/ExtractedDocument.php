<?php

namespace App\Services\Knowledge\Extraction;

use JsonException;

/**
 * The intermediate format between a file and its sections. Stored as JSON next
 * to the original, so chunking can be redone without extracting again.
 */
final readonly class ExtractedDocument
{
    /**
     * @param  list<Block>  $blocks
     * @param  list<int>  $flaggedPages  pages with too little text: probably scanned
     */
    public function __construct(
        public array $blocks,
        public ?int $pageCount = null,
        public array $flaggedPages = [],
        public string $method = 'cli',
    ) {}

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode([
            'method' => $this->method,
            'page_count' => $this->pageCount,
            'flagged_pages' => $this->flaggedPages,
            'blocks' => array_map(fn (Block $block): array => $block->toArray(), $this->blocks),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws JsonException
     */
    public static function fromJson(string $json): self
    {
        /** @var array{method: string, page_count: int|null, flagged_pages: list<int>, blocks: list<array{type: string, text: string, page?: int|null, level?: int, rows?: list<list<string>>}>} $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return new self(
            array_map(Block::fromArray(...), $data['blocks']),
            $data['page_count'],
            $data['flagged_pages'],
            $data['method'],
        );
    }

    /**
     * Replace the blocks of some pages, e.g. with what the LLM read from a scan.
     *
     * @param  array<int, list<Block>>  $byPage
     */
    public function withPages(array $byPage, string $method): self
    {
        $blocks = [];
        $replaced = [];

        foreach ($this->blocks as $block) {
            if ($block->page !== null && isset($byPage[$block->page])) {
                if (! isset($replaced[$block->page])) {
                    array_push($blocks, ...$byPage[$block->page]);
                    $replaced[$block->page] = true;
                }

                continue;
            }

            $blocks[] = $block;
        }

        // Pages that had no blocks at all (blank scans) go in page order.
        foreach ($byPage as $page => $pageBlocks) {
            if (! isset($replaced[$page])) {
                $blocks = $this->insertAtPage($blocks, $page, $pageBlocks);
            }
        }

        return new self($blocks, $this->pageCount, array_values(array_diff($this->flaggedPages, array_keys($byPage))), $method);
    }

    public function characterCount(): int
    {
        return array_sum(array_map(fn (Block $block): int => mb_strlen($block->text), $this->blocks));
    }

    /**
     * @param  list<Block>  $blocks
     * @param  list<Block>  $insert
     * @return list<Block>
     */
    private function insertAtPage(array $blocks, int $page, array $insert): array
    {
        foreach ($blocks as $index => $block) {
            if ($block->page !== null && $block->page > $page) {
                return [...array_slice($blocks, 0, $index), ...$insert, ...array_slice($blocks, $index)];
            }
        }

        return [...$blocks, ...$insert];
    }
}
