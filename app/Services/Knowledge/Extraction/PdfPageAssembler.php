<?php

namespace App\Services\Knowledge\Extraction;

/**
 * Assembles one page's rows of positioned text into blocks. Holds the block
 * being built — a paragraph, a list or a table — and closes it when the
 * layout says something else starts.
 *
 * @phpstan-type Line array{top: int, left: int, width: int, height: int, size: float, bold: bool, text: string}
 */
class PdfPageAssembler
{
    private const string LIST_MARKER = '/^\s*(?:[•●◦▪\-–*]|\d{1,2}[.)]|[a-z][.)])\s+/u';

    /** @var list<Block> */
    private array $blocks = [];

    private ?string $paragraph = null;

    /** @var list<string> */
    private array $listItems = [];

    /** @var list<list<string>> */
    private array $tableRows = [];

    /** @var list<int> where the table's columns start */
    private array $columns = [];

    /** @var Line|null */
    private ?array $previous = null;

    /**
     * @param  callable(Line): ?int  $headingLevel
     */
    public function __construct(
        private readonly int $page,
        private readonly mixed $headingLevel,
    ) {}

    /**
     * @param  non-empty-list<Line>  $row  the lines at one height, left to right
     */
    public function add(array $row): void
    {
        $first = $row[0];
        $gap = $this->previous === null ? 0 : $first['top'] - ($this->previous['top'] + $this->previous['height']);
        $level = ($this->headingLevel)($first);

        if (count($row) >= 2) {
            $this->closeParagraph();
            $this->closeList();

            if ($this->tableRows === []) {
                $this->columns = array_map(fn (array $cell): int => $cell['left'], $row);
            }

            $this->tableRows[] = array_map(fn (array $cell): string => $cell['text'], $row);
            $this->previous = $first;

            return;
        }

        // A single line directly under table rows is a row whose cells
        // pdftohtml merged into one.
        if ($this->tableRows !== [] && $this->previous !== null && $gap <= $first['height'] && $level === null) {
            $this->tableRows[] = $this->splitMergedCells($first);
            $this->previous = $first;

            return;
        }

        $this->closeTable();

        if ($level !== null) {
            $this->closeParagraph();
            $this->closeList();
            $this->blocks[] = Block::heading($first['text'], $level, $this->page);
            $this->previous = $first;

            return;
        }

        if (preg_match(self::LIST_MARKER, $first['text']) === 1) {
            $this->closeParagraph();
            $this->listItems[] = trim((string) preg_replace(self::LIST_MARKER, '', $first['text']));
            $this->previous = $first;

            return;
        }

        $continues = $this->previous !== null
            && $gap <= $first['height'] * 0.8
            && abs($first['size'] - $this->previous['size']) < 0.5;

        if ($this->listItems !== [] && $continues && $first['left'] > $this->previous['left'] - 2) {
            $last = array_key_last($this->listItems);
            $this->listItems[$last] = self::join($this->listItems[$last], $first['text']);
            $this->previous = $first;

            return;
        }

        $this->closeList();

        if ($this->paragraph !== null && $continues) {
            $this->paragraph = self::join($this->paragraph, $first['text']);
        } else {
            $this->closeParagraph();
            $this->paragraph = $first['text'];
        }

        $this->previous = $first;
    }

    /**
     * @return list<Block>
     */
    public function finish(): array
    {
        $this->closeParagraph();
        $this->closeList();
        $this->closeTable();

        return $this->blocks;
    }

    /**
     * Undo hyphenation at a line break: "verlof-" + "aanvraag".
     */
    public static function join(string $text, string $next): string
    {
        if (preg_match('/\p{L}-$/u', $text) === 1 && preg_match('/^\p{Ll}/u', $next) === 1) {
            return mb_substr($text, 0, -1).$next;
        }

        return $text.' '.$next;
    }

    private function closeParagraph(): void
    {
        if ($this->paragraph !== null) {
            $this->blocks[] = Block::paragraph($this->paragraph, $this->page);
            $this->paragraph = null;
        }
    }

    private function closeList(): void
    {
        if ($this->listItems !== []) {
            $this->blocks[] = Block::list(implode("\n", array_map(fn (string $item): string => '- '.$item, $this->listItems)), $this->page);
            $this->listItems = [];
        }
    }

    private function closeTable(): void
    {
        if (count($this->tableRows) >= 2) {
            $this->blocks[] = Block::table($this->tableRows, $this->page);
        } else {
            // One row side by side is not a table, just text in columns.
            foreach ($this->tableRows as $row) {
                $this->blocks[] = Block::paragraph(implode(' ', $row), $this->page);
            }
        }

        $this->tableRows = [];
        $this->columns = [];
    }

    /**
     * Split a merged line where the table's columns start, estimating the
     * position from the line's average character width.
     *
     * @param  Line  $line
     * @return list<string>
     */
    private function splitMergedCells(array $line): array
    {
        $text = $line['text'];
        $length = mb_strlen($text);
        $charWidth = $length > 0 ? $line['width'] / $length : 0;

        if ($charWidth <= 0) {
            return [$text];
        }

        $cells = [];
        $start = 0;

        foreach (array_slice($this->columns, 1) as $column) {
            $at = (int) round(($column - $line['left']) / $charWidth);

            if ($at <= $start || $at >= $length) {
                continue;
            }

            // Cut at the nearest space before the estimated column start.
            $space = mb_strrpos(mb_substr($text, 0, $at + 1), ' ');
            $cut = $space !== false && $space > $start ? $space : $at;

            $cells[] = trim(mb_substr($text, $start, $cut - $start));
            $start = $cut;
        }

        $cells[] = trim(mb_substr($text, $start));

        return array_values(array_filter($cells, fn (string $cell): bool => $cell !== ''));
    }
}
