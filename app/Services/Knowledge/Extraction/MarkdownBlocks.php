<?php

namespace App\Services\Knowledge\Extraction;

/**
 * Parses the small markdown subset the LLM is asked to transcribe scans in:
 * # headings, - or 1. list items, | tables | and blank-line paragraphs.
 */
class MarkdownBlocks
{
    /** @var list<Block> */
    private array $blocks = [];

    /** @var list<string> */
    private array $paragraph = [];

    /** @var list<string> */
    private array $list = [];

    /** @var list<list<string>> */
    private array $table = [];

    private function __construct(private readonly ?int $page) {}

    /**
     * @return list<Block>
     */
    public static function parse(string $markdown, ?int $page = null): array
    {
        $parser = new self($page);

        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $parser->line(trim($line));
        }

        $parser->close();

        return $parser->blocks;
    }

    private function line(string $line): void
    {
        if ($line === '') {
            $this->close();

            return;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $m) === 1) {
            $this->close();
            $this->blocks[] = Block::heading(trim($m[2]), strlen($m[1]), $this->page);

            return;
        }

        if (str_starts_with($line, '|')) {
            if ($this->paragraph !== [] || $this->list !== []) {
                $this->close();
            }

            // The |---|---| separator row carries no content.
            if (preg_match('/^\|?\s*:?-{2,}/u', $line) !== 1) {
                $this->table[] = array_map('trim', explode('|', trim($line, '|')));
            }

            return;
        }

        if (preg_match('/^(?:[-*•]|\d{1,2}[.)])\s+(.+)$/u', $line, $m) === 1) {
            if ($this->paragraph !== [] || $this->table !== []) {
                $this->close();
            }
            $this->list[] = '- '.trim($m[1]);

            return;
        }

        if ($this->table !== [] || $this->list !== []) {
            $this->close();
        }

        $this->paragraph[] = $line;
    }

    private function close(): void
    {
        if ($this->paragraph !== []) {
            $this->blocks[] = Block::paragraph(implode(' ', $this->paragraph), $this->page);
        }

        if ($this->list !== []) {
            $this->blocks[] = Block::list(implode("\n", $this->list), $this->page);
        }

        if (count($this->table) >= 2) {
            $this->blocks[] = Block::table($this->table, $this->page);
        } elseif ($this->table !== []) {
            $this->blocks[] = Block::paragraph(implode(' ', $this->table[0]), $this->page);
        }

        $this->paragraph = $this->list = $this->table = [];
    }
}
