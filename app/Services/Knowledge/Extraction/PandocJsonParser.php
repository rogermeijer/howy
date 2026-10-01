<?php

namespace App\Services\Knowledge\Extraction;

use JsonException;

/**
 * Turns pandoc's JSON AST of a .docx into structural blocks. Word keeps real
 * headings, lists and tables, so nothing has to be guessed here; Word has no
 * fixed pages, so blocks carry no page number and the section is the locator.
 */
class PandocJsonParser
{
    /**
     * @return list<Block>
     *
     * @throws JsonException
     */
    public function parse(string $json): array
    {
        /** @var array{blocks: list<array<string, mixed>>} $ast */
        $ast = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $this->blocks($ast['blocks']);
    }

    /**
     * @param  array<mixed>  $nodes
     * @return list<Block>
     */
    private function blocks(array $nodes): array
    {
        $blocks = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = $node['t'] ?? null;
            $content = $node['c'] ?? null;

            switch ($type) {
                case 'Header':
                    /** @var array{0: int, 1: mixed, 2: list<mixed>} $content */
                    $text = $this->inline($content[2]);
                    if ($text !== '') {
                        $blocks[] = Block::heading($text, (int) $content[0]);
                    }
                    break;

                case 'Para':
                case 'Plain':
                    $text = $this->inline(is_array($content) ? $content : []);
                    if ($text !== '') {
                        $blocks[] = Block::paragraph($text);
                    }
                    break;

                case 'BulletList':
                case 'OrderedList':
                    /** @var list<list<mixed>> $items */
                    $items = $type === 'OrderedList' && is_array($content) ? ($content[1] ?? []) : (is_array($content) ? $content : []);
                    $lines = [];
                    foreach ($items as $index => $item) {
                        $marker = $type === 'OrderedList' ? ($index + 1).'.' : '-';
                        $lines[] = $marker.' '.implode(' ', array_map(fn (Block $block): string => $block->text, $this->blocks($item)));
                    }
                    if ($lines !== []) {
                        $blocks[] = Block::list(implode("\n", $lines));
                    }
                    break;

                case 'Table':
                    $rows = $this->tableRows(is_array($content) ? $content : []);
                    if ($rows !== []) {
                        $blocks[] = Block::table($rows);
                    }
                    break;

                case 'BlockQuote':
                case 'Div':
                    $children = $type === 'Div' && is_array($content) ? ($content[1] ?? []) : $content;
                    array_push($blocks, ...$this->blocks(is_array($children) ? $children : []));
                    break;

                case 'LineBlock':
                    $lines = array_map(fn ($line): string => $this->inline(is_array($line) ? $line : []), is_array($content) ? $content : []);
                    $blocks[] = Block::paragraph(implode("\n", $lines));
                    break;

                case 'CodeBlock':
                    if (is_array($content) && is_string($content[1] ?? null)) {
                        $blocks[] = Block::paragraph($content[1]);
                    }
                    break;
            }
        }

        return $blocks;
    }

    /**
     * Pandoc ≥ 2.10 tables: [attr, caption, colspecs, head, bodies, foot].
     *
     * @param  array<mixed>  $table
     * @return list<list<string>>
     */
    private function tableRows(array $table): array
    {
        $rows = [];
        $head = $table[3] ?? null;
        $bodies = $table[4] ?? [];
        $foot = $table[5] ?? null;

        if (is_array($head)) {
            array_push($rows, ...$this->rows($head[1] ?? []));
        }

        foreach (is_array($bodies) ? $bodies : [] as $body) {
            if (is_array($body)) {
                array_push($rows, ...$this->rows($body[2] ?? []));
                array_push($rows, ...$this->rows($body[3] ?? []));
            }
        }

        if (is_array($foot)) {
            array_push($rows, ...$this->rows($foot[1] ?? []));
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function rows(mixed $rows): array
    {
        $result = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $cells = [];

            foreach (is_array($row) && is_array($row[1] ?? null) ? $row[1] : [] as $cell) {
                // A cell is [attr, alignment, rowspan, colspan, blocks].
                $blocks = is_array($cell) && is_array($cell[4] ?? null) ? $cell[4] : [];
                $cells[] = implode(' ', array_map(fn (Block $block): string => $block->text, $this->blocks($blocks)));
            }

            if ($cells !== []) {
                $result[] = $cells;
            }
        }

        return $result;
    }

    /**
     * @param  array<mixed>  $inlines
     */
    private function inline(array $inlines): string
    {
        $text = '';

        foreach ($inlines as $inline) {
            if (! is_array($inline)) {
                continue;
            }

            $content = $inline['c'] ?? null;

            $text .= match ($inline['t'] ?? null) {
                'Str' => is_string($content) ? $content : '',
                'Space', 'SoftBreak' => ' ',
                'LineBreak' => "\n",
                'Emph', 'Strong', 'Underline', 'Strikeout', 'Superscript', 'Subscript', 'SmallCaps' => $this->inline(is_array($content) ? $content : []),
                'Quoted' => '"'.$this->inline(is_array($content) && is_array($content[1] ?? null) ? $content[1] : []).'"',
                'Link', 'Span' => $this->inline(is_array($content) && is_array($content[1] ?? null) ? $content[1] : []),
                'Code' => is_array($content) && is_string($content[1] ?? null) ? $content[1] : '',
                default => '',
            };
        }

        return trim((string) preg_replace('/[ \t]+/u', ' ', $text));
    }
}
