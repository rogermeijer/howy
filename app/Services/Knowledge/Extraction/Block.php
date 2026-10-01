<?php

namespace App\Services\Knowledge\Extraction;

/**
 * One structural element of an extracted document. Headings carry a level;
 * tables keep their rows so they can be chunked (and repeated) row by row.
 */
final readonly class Block
{
    public const string HEADING = 'heading';

    public const string PARAGRAPH = 'paragraph';

    public const string LIST = 'list';

    public const string TABLE = 'table';

    /**
     * @param  list<list<string>>  $rows  table rows, the first one being the header
     */
    public function __construct(
        public string $type,
        public string $text,
        public ?int $page = null,
        public int $level = 0,
        public array $rows = [],
    ) {}

    public static function heading(string $text, int $level, ?int $page = null): self
    {
        return new self(self::HEADING, $text, $page, $level);
    }

    public static function paragraph(string $text, ?int $page = null): self
    {
        return new self(self::PARAGRAPH, $text, $page);
    }

    public static function list(string $text, ?int $page = null): self
    {
        return new self(self::LIST, $text, $page);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    public static function table(array $rows, ?int $page = null): self
    {
        return new self(self::TABLE, self::renderTable($rows), $page, 0, $rows);
    }

    public function isHeading(): bool
    {
        return $this->type === self::HEADING;
    }

    /**
     * A markdown table: readable for people and models alike.
     *
     * @param  list<list<string>>  $rows
     */
    public static function renderTable(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $width = max(array_map(count(...), $rows));
        $line = fn (array $cells): string => '| '.implode(' | ', array_map(
            fn (string $cell): string => str_replace('|', '\|', trim($cell)),
            array_pad($cells, $width, ''),
        )).' |';

        $lines = [$line($rows[0]), '|'.str_repeat(' --- |', $width)];

        foreach (array_slice($rows, 1) as $row) {
            $lines[] = $line($row);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{type: string, text: string, page: int|null, level: int, rows: list<list<string>>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'page' => $this->page,
            'level' => $this->level,
            'rows' => $this->rows,
        ];
    }

    /**
     * @param  array{type: string, text: string, page?: int|null, level?: int, rows?: list<list<string>>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['type'], $data['text'], $data['page'] ?? null, $data['level'] ?? 0, $data['rows'] ?? []);
    }
}
