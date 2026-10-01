<?php

namespace App\Services\Knowledge\Structure;

use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\Extraction\ExtractedDocument;
use App\Services\Knowledge\TokenEstimator;

/**
 * Builds the section tree from extracted blocks: every heading opens a section
 * whose parent is the nearest earlier heading of a higher level. Text before
 * the first heading becomes an untitled introduction section.
 */
class StructureBuilder
{
    public const string SEPARATOR = ' › ';

    public function __construct(private readonly TokenEstimator $tokens) {}

    /**
     * @return list<SectionDraft>
     */
    public function build(ExtractedDocument $document): array
    {
        /** @var list<array{level: int, heading: string|null, page: int|null, blocks: list<Block>}> $raw */
        $raw = [];
        $open = null;

        foreach ($document->blocks as $block) {
            if ($block->isHeading()) {
                if ($open !== null) {
                    $raw[] = $open;
                }
                $open = ['level' => max(1, $block->level), 'heading' => $block->text, 'page' => $block->page, 'blocks' => []];

                continue;
            }

            $open ??= ['level' => 1, 'heading' => null, 'page' => $block->page, 'blocks' => []];
            $open['blocks'][] = $block;
        }

        if ($open !== null) {
            $raw[] = $open;
        }

        $drafts = [];
        /** @var array<int, array{ordinal: int, heading: string|null, path: string}> $stack level => open section */
        $stack = [];

        foreach ($raw as $ordinal => $section) {
            $level = $section['level'];

            // Close every open section at this level or deeper.
            $stack = array_filter($stack, fn (int $open): bool => $open < $level, ARRAY_FILTER_USE_KEY);
            ksort($stack);
            $parent = $stack === [] ? null : $stack[array_key_last($stack)];

            $heading = $section['heading'];
            $path = $heading === null
                ? ($parent['path'] ?? '')
                : ($parent === null ? $heading : $parent['path'].self::SEPARATOR.$heading);

            $body = implode("\n\n", array_map(fn (Block $block): string => $block->text, $section['blocks']));
            $pages = array_values(array_filter([$section['page'], ...array_map(fn (Block $block): ?int => $block->page, $section['blocks'])], fn (?int $page): bool => $page !== null));

            $drafts[] = new SectionDraft(
                ordinal: $ordinal,
                level: $level,
                heading: $heading,
                headingPath: $path,
                parentOrdinal: $parent['ordinal'] ?? null,
                blocks: $section['blocks'],
                pageFrom: $pages === [] ? null : min($pages),
                pageTo: $pages === [] ? null : max($pages),
                contentHash: self::hash($body),
                tokenCount: $this->tokens->count($body),
            );

            if ($heading !== null) {
                $stack[$level] = ['ordinal' => $ordinal, 'heading' => $heading, 'path' => $path];
            }
        }

        return $drafts;
    }

    /**
     * Hash of normalised text: whitespace differences are not changes.
     */
    public static function hash(string $text): string
    {
        return hash('sha256', trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
