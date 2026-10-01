<?php

namespace App\Services\Knowledge\Chunking;

use App\Enums\ChunkKind;
use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\Structure\SectionDraft;
use App\Services\Knowledge\TokenEstimator;

/**
 * Splits a section's own text into chunks along its structure, never across a
 * section boundary, so every chunk has exactly one section (and heading path)
 * to point back to.
 *
 * - paragraphs and lists are packed up to the target size;
 * - a block larger than the maximum is split at sentence boundaries, with a
 *   small overlap so a sentence never loses its lead-in;
 * - a table is its own chunk; a large table is split per rows, each part
 *   repeating the header row.
 *
 * Sections are not merged, even small ones: the heading path and context line
 * that go with every chunk carry enough to make a short chunk findable, and
 * merging would blur which section an answer came from.
 */
class StructuralChunker
{
    private int $target;

    private int $max;

    private int $overlap;

    public function __construct(private readonly TokenEstimator $tokens)
    {
        $this->target = (int) config('knowledge.chunking.target_tokens');
        $this->max = (int) config('knowledge.chunking.max_tokens');
        $this->overlap = (int) config('knowledge.chunking.overlap_tokens');
    }

    /**
     * @return list<ChunkDraft>
     */
    public function chunk(SectionDraft $section): array
    {
        $chunks = [];
        /** @var list<Block> $buffer */
        $buffer = [];
        $bufferTokens = 0;

        $flush = function () use (&$chunks, &$buffer, &$bufferTokens): void {
            if ($buffer !== []) {
                $kind = count($buffer) === 1 && $buffer[0]->type === Block::LIST ? ChunkKind::List : ChunkKind::Text;
                $chunks[] = $this->draft($kind, $buffer);
                $buffer = [];
                $bufferTokens = 0;
            }
        };

        foreach ($section->blocks as $block) {
            if ($block->type === Block::TABLE) {
                $flush();
                array_push($chunks, ...$this->tableChunks($block));

                continue;
            }

            $tokens = $this->tokens->count($block->text);

            if ($tokens > $this->max) {
                $flush();
                array_push($chunks, ...$this->splitLongBlock($block));

                continue;
            }

            if ($bufferTokens + $tokens > $this->target && $buffer !== []) {
                $flush();
            }

            $buffer[] = $block;
            $bufferTokens += $tokens;
        }

        $flush();

        return $chunks;
    }

    /**
     * @param  non-empty-list<Block>  $blocks
     */
    private function draft(ChunkKind $kind, array $blocks): ChunkDraft
    {
        $content = implode("\n\n", array_map(fn (Block $block): string => $block->text, $blocks));
        $pages = array_values(array_filter(array_map(fn (Block $block): ?int => $block->page, $blocks), fn (?int $page): bool => $page !== null));

        return new ChunkDraft(
            $kind,
            $content,
            $pages === [] ? null : min($pages),
            $pages === [] ? null : max($pages),
            $this->tokens->count($content),
        );
    }

    /**
     * @return list<ChunkDraft>
     */
    private function splitLongBlock(Block $block): array
    {
        $sentences = preg_split('/(?<=[.!?;:])\s+(?=\S)/u', $block->text, flags: PREG_SPLIT_NO_EMPTY) ?: [$block->text];
        $chunks = [];
        $current = [];
        $currentTokens = 0;

        foreach ($sentences as $sentence) {
            $tokens = $this->tokens->count($sentence);

            if ($currentTokens + $tokens > $this->target && $current !== []) {
                $chunks[] = implode(' ', $current);

                // Carry the last sentences (up to the overlap) into the next chunk.
                $carry = [];
                $carryTokens = 0;

                foreach (array_reverse($current) as $previous) {
                    $previousTokens = $this->tokens->count($previous);

                    if ($carryTokens + $previousTokens > $this->overlap) {
                        break;
                    }

                    array_unshift($carry, $previous);
                    $carryTokens += $previousTokens;
                }

                $current = $carry;
                $currentTokens = $carryTokens;
            }

            $current[] = $sentence;
            $currentTokens += $tokens;
        }

        $chunks[] = implode(' ', $current);

        return array_map(fn (string $text): ChunkDraft => new ChunkDraft(
            $block->type === Block::LIST ? ChunkKind::List : ChunkKind::Text,
            $text,
            $block->page,
            $block->page,
            $this->tokens->count($text),
        ), $chunks);
    }

    /**
     * @return list<ChunkDraft>
     */
    private function tableChunks(Block $table): array
    {
        if ($this->tokens->count($table->text) <= $this->max || count($table->rows) < 3) {
            return [new ChunkDraft(ChunkKind::Table, $table->text, $table->page, $table->page, $this->tokens->count($table->text))];
        }

        $header = $table->rows[0];
        $parts = [];
        $rows = [];

        foreach (array_slice($table->rows, 1) as $row) {
            $candidate = Block::renderTable([$header, ...$rows, $row]);

            if ($rows !== [] && $this->tokens->count($candidate) > $this->target) {
                $parts[] = Block::renderTable([$header, ...$rows]);
                $rows = [];
            }

            $rows[] = $row;
        }

        $parts[] = Block::renderTable([$header, ...$rows]);

        return array_map(fn (string $text): ChunkDraft => new ChunkDraft(ChunkKind::Table, $text, $table->page, $table->page, $this->tokens->count($text)), $parts);
    }
}
