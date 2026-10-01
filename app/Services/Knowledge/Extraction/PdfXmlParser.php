<?php

namespace App\Services\Knowledge\Extraction;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Turns `pdftohtml -xml` output into structural blocks.
 *
 * A PDF has no structure, only positioned text. This recovers it from layout:
 * - headings are lines set larger than the body text (the most common size by
 *   character count), or bold numbered lines ("2.1 Vakantiedagen");
 * - tables are runs of rows where text sits side by side at the same height;
 *   a table that continues on the next page with the same header is joined;
 * - running headers, footers and page numbers repeated across pages are dropped.
 *
 * @phpstan-import-type Line from PdfPageAssembler
 */
class PdfXmlParser
{
    /** Lines whose tops differ less than this many points share a row. */
    private const int ROW_TOLERANCE = 3;

    private const string NUMBERED_HEADING = '/^\d{1,2}(?:\.\d{1,2}){0,3}\.?\s+\S/u';

    /**
     * @return array{blocks: list<Block>, page_count: int, flagged_pages: list<int>}
     */
    public function parse(string $xml, int $minCharsPerPage = 50): array
    {
        $pages = $this->readPages($xml);
        $pages = $this->dropRunningHeadersAndFooters($pages);

        $bodySize = $this->bodySize($pages);
        $headingSizes = $this->headingSizes($pages, $bodySize);

        $blocks = [];
        $flagged = [];

        foreach ($pages as $number => $page) {
            $chars = array_sum(array_map(fn (array $line): int => mb_strlen($line['text']), $page['lines']));

            if ($chars < $minCharsPerPage) {
                $flagged[] = $number;
            }

            $blocks = $this->appendPage($blocks, $this->pageBlocks($page['lines'], $number, $bodySize, $headingSizes));
        }

        return ['blocks' => $blocks, 'page_count' => count($pages), 'flagged_pages' => $flagged];
    }

    /**
     * @return array<int, array{height: int, lines: list<Line>}>
     */
    private function readPages(string $xml): array
    {
        $dom = new DOMDocument;

        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The PDF text could not be read.');
        }

        $sizes = [];

        foreach ($dom->getElementsByTagName('fontspec') as $font) {
            $sizes[$font->getAttribute('id')] = (float) $font->getAttribute('size');
        }

        $pages = [];

        foreach ($dom->getElementsByTagName('page') as $pageElement) {
            $lines = [];

            foreach ($pageElement->getElementsByTagName('text') as $text) {
                $content = $this->squish($text->textContent);

                if ($content === '') {
                    continue;
                }

                $lines[] = [
                    'top' => (int) $text->getAttribute('top'),
                    'left' => (int) $text->getAttribute('left'),
                    'width' => (int) $text->getAttribute('width'),
                    'height' => max(1, (int) $text->getAttribute('height')),
                    'size' => $sizes[$text->getAttribute('font')] ?? 0.0,
                    'bold' => $this->isBold($text, $content),
                    'text' => $content,
                ];
            }

            usort($lines, fn (array $a, array $b): int => [$a['top'], $a['left']] <=> [$b['top'], $b['left']]);

            $pages[(int) $pageElement->getAttribute('number')] = [
                'height' => (int) $pageElement->getAttribute('height'),
                'lines' => $lines,
            ];
        }

        return $pages;
    }

    private function isBold(DOMElement $text, string $content): bool
    {
        $bold = '';

        foreach ($text->getElementsByTagName('b') as $b) {
            $bold .= $b->textContent;
        }

        return $bold !== '' && $this->squish($bold) === $content;
    }

    /**
     * Text in the top or bottom band that repeats on most pages is a running
     * header or footer; a bare number there is a page number.
     *
     * @param  array<int, array{height: int, lines: list<Line>}>  $pages
     * @return array<int, array{height: int, lines: list<Line>}>
     */
    private function dropRunningHeadersAndFooters(array $pages): array
    {
        $band = fn (array $line, int $height): bool => $line['top'] < $height * 0.07 || $line['top'] > $height * 0.92;
        $normalise = fn (string $text): string => (string) preg_replace('/\d+/', '#', mb_strtolower($text));

        $counts = [];

        foreach ($pages as $page) {
            $seen = [];

            foreach ($page['lines'] as $line) {
                if ($band($line, $page['height'])) {
                    $seen[$normalise($line['text'])] = true;
                }
            }

            foreach (array_keys($seen) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $repeated = count($pages) >= 3
            ? array_keys(array_filter($counts, fn (int $count): bool => $count >= count($pages) * 0.5))
            : [];

        foreach ($pages as $number => $page) {
            $pages[$number]['lines'] = array_values(array_filter(
                $page['lines'],
                fn (array $line): bool => ! ($band($line, $page['height']) && (
                    in_array($normalise($line['text']), $repeated, true)
                    || preg_match('/^(?:\D{0,12}\s)?\d{1,4}(?:\s*(?:\/|van|of)\s*\d{1,4})?$/iu', $line['text']) === 1
                )),
            ));
        }

        return $pages;
    }

    /**
     * The body text size: the size most characters are set in.
     *
     * @param  array<int, array{height: int, lines: list<Line>}>  $pages
     */
    private function bodySize(array $pages): float
    {
        $weights = [];

        foreach ($pages as $page) {
            foreach ($page['lines'] as $line) {
                $key = (string) $line['size'];
                $weights[$key] = ($weights[$key] ?? 0) + mb_strlen($line['text']);
            }
        }

        if ($weights === []) {
            return 0.0;
        }

        arsort($weights);

        return (float) array_key_first($weights);
    }

    /**
     * Sizes clearly larger than the body, largest first: index + 1 is the level.
     *
     * @param  array<int, array{height: int, lines: list<Line>}>  $pages
     * @return list<float>
     */
    private function headingSizes(array $pages, float $bodySize): array
    {
        $sizes = [];

        foreach ($pages as $page) {
            foreach ($page['lines'] as $line) {
                if ($line['size'] > $bodySize * 1.1 && mb_strlen($line['text']) <= 150) {
                    $sizes[(string) $line['size']] = $line['size'];
                }
            }
        }

        rsort($sizes);

        return array_slice($sizes, 0, 5);
    }

    /**
     * @param  list<Line>  $lines
     * @param  list<float>  $headingSizes
     * @return list<Block>
     */
    private function pageBlocks(array $lines, int $page, float $bodySize, array $headingSizes): array
    {
        $assembler = new PdfPageAssembler($page, fn (array $line): ?int => $this->headingLevel($line, $bodySize, $headingSizes));

        foreach ($this->rows($lines) as $row) {
            $assembler->add($row);
        }

        return $assembler->finish();
    }

    /**
     * Joins the next page's blocks, continuing a table that runs over the page
     * break when it repeats the same header.
     *
     * @param  list<Block>  $blocks
     * @param  list<Block>  $next
     * @return list<Block>
     */
    private function appendPage(array $blocks, array $next): array
    {
        $last = $blocks === [] ? null : $blocks[array_key_last($blocks)];
        $first = $next[0] ?? null;

        if ($last !== null && $first !== null && $last->type === Block::TABLE && $first->type === Block::TABLE
            && ($last->rows[0] ?? null) === ($first->rows[0] ?? null)) {
            $blocks[array_key_last($blocks)] = Block::table([...$last->rows, ...array_slice($first->rows, 1)], $last->page);
            array_shift($next);
        }

        return [...$blocks, ...$next];
    }

    /**
     * @param  list<Line>  $lines
     * @return list<non-empty-list<Line>>
     */
    private function rows(array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $last = array_key_last($rows);

            if ($last !== null && abs($rows[$last][0]['top'] - $line['top']) <= self::ROW_TOLERANCE) {
                $rows[$last][] = $line;
            } else {
                $rows[] = [$line];
            }
        }

        foreach ($rows as $index => $row) {
            usort($row, fn (array $a, array $b): int => $a['left'] <=> $b['left']);
            $rows[$index] = $row;
        }

        return $rows;
    }

    /**
     * @param  Line  $line
     * @param  list<float>  $headingSizes
     */
    private function headingLevel(array $line, float $bodySize, array $headingSizes): ?int
    {
        if (mb_strlen($line['text']) > 150) {
            return null;
        }

        $index = array_search($line['size'], $headingSizes, true);

        if ($index !== false) {
            return $index + 1;
        }

        // A bold, numbered line at body size: a low-level heading.
        if ($line['bold'] && abs($line['size'] - $bodySize) < 0.5 && preg_match(self::NUMBERED_HEADING, $line['text']) === 1) {
            return count($headingSizes) + 1;
        }

        return null;
    }

    private function squish(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
