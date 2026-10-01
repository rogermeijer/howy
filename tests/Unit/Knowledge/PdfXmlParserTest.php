<?php

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\Extraction\PdfXmlParser;
use PHPUnit\Framework\TestCase;

class PdfXmlParserTest extends TestCase
{
    /**
     * @return array{blocks: list<Block>, page_count: int, flagged_pages: list<int>}
     */
    private function parseFixture(): array
    {
        return (new PdfXmlParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/Knowledge/personeelshandboek.pdf.xml'));
    }

    public function test_headings_get_levels_from_their_font_size(): void
    {
        $headings = array_values(array_filter($this->parseFixture()['blocks'], fn (Block $block): bool => $block->isHeading()));

        $this->assertSame(
            [[1, 'Personeelshandboek'], [1, 'Personeelshandboek Noordkade'], [2, '1 Werktijden'], [3, '1.1 Weekendwerk']],
            array_map(fn (Block $block): array => [$block->level, $block->text], array_slice($headings, 0, 4)),
        );
        $this->assertSame([2, '4 Onkosten'], [$headings[array_key_last($headings)]->level, $headings[array_key_last($headings)]->text]);
    }

    public function test_wrapped_lines_become_one_paragraph_on_its_page(): void
    {
        $paragraph = $this->first(fn (Block $block): bool => str_starts_with($block->text, 'Een medewerker die ziek is'));

        $this->assertSame(Block::PARAGRAPH, $paragraph->type);
        $this->assertSame(2, $paragraph->page);
        $this->assertStringContainsString('telefonisch bij de eigen leidinggevende. Een ziekmelding per e-mail', $paragraph->text);
        $this->assertStringEndsWith('over het verwachte herstel.', $paragraph->text);
    }

    public function test_a_table_continuing_on_the_next_page_is_joined_and_merged_cells_are_split(): void
    {
        $tables = array_values(array_filter($this->parseFixture()['blocks'], fn (Block $block): bool => $block->type === Block::TABLE));

        $this->assertCount(1, $tables);
        $this->assertSame(1, $tables[0]->page);
        $this->assertSame([
            ['Dienstjaren', 'Extra vakantiedagen'],
            ['5 tot 10 jaar', '1 dag'],
            ['10 tot 20 jaar', '2 dagen'],
            ['20 jaar of langer', '3 dagen'],
        ], $tables[0]->rows);
    }

    public function test_pages_without_a_text_layer_are_flagged(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <pdf2xml>
        <page number="1" height="1188" width="918">
            <fontspec id="0" size="12" family="Times" color="#000000"/>
            <text top="100" left="100" width="400" height="12" font="0">Een pagina met gewone tekst die lang genoeg is om niet op te vallen.</text>
        </page>
        <page number="2" height="1188" width="918"></page>
        </pdf2xml>
        XML;

        $result = (new PdfXmlParser)->parse($xml);

        $this->assertSame(2, $result['page_count']);
        $this->assertSame([2], $result['flagged_pages']);
    }

    public function test_running_footers_and_page_numbers_are_dropped(): void
    {
        $page = fn (int $n): string => <<<XML
        <page number="{$n}" height="1000" width="800">
            <text top="20" left="100" width="300" height="12" font="0">Noordkade · Personeelshandboek</text>
            <text top="200" left="100" width="500" height="12" font="0">Inhoud van pagina {$n} met genoeg tekst om als alinea te tellen.</text>
            <text top="960" left="390" width="20" height="12" font="0">{$n}</text>
        </page>
        XML;

        $xml = '<?xml version="1.0"?><pdf2xml><fontspec id="0" size="12" family="Times" color="#000"/>'.$page(1).$page(2).$page(3).'</pdf2xml>';

        $texts = array_map(fn (Block $block): string => $block->text, (new PdfXmlParser)->parse($xml)['blocks']);

        $this->assertSame([
            'Inhoud van pagina 1 met genoeg tekst om als alinea te tellen.',
            'Inhoud van pagina 2 met genoeg tekst om als alinea te tellen.',
            'Inhoud van pagina 3 met genoeg tekst om als alinea te tellen.',
        ], $texts);
    }

    private function first(callable $predicate): Block
    {
        foreach ($this->parseFixture()['blocks'] as $block) {
            if ($predicate($block)) {
                return $block;
            }
        }

        $this->fail('No matching block.');
    }
}
