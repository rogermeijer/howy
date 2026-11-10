<?php

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\Extraction\PandocJsonParser;
use PHPUnit\Framework\TestCase;

class PandocJsonParserTest extends TestCase
{
    public function test_word_headings_paragraphs_and_tables_are_kept(): void
    {
        $blocks = (new PandocJsonParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/Knowledge/personeelshandboek.docx.json'));

        $headings = array_values(array_filter($blocks, fn (Block $block): bool => $block->isHeading()));
        $this->assertSame([1, 'Personeelshandboek Noordkade'], [$headings[0]->level, $headings[0]->text]);
        $this->assertSame([3, '2.1 Vakantiedagen'], [$headings[5]->level, $headings[5]->text]);

        $table = array_values(array_filter($blocks, fn (Block $block): bool => $block->type === Block::TABLE))[0];
        $this->assertSame(['20 jaar of langer', '3 dagen'], $table->rows[3]);

        // Word has no fixed pages.
        $this->assertNull($blocks[0]->page);
    }

    public function test_lists_become_one_block_with_markers(): void
    {
        $json = json_encode(['blocks' => [
            ['t' => 'BulletList', 'c' => [
                [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Eerste']]]],
                [['t' => 'Plain', 'c' => [['t' => 'Str', 'c' => 'Tweede'], ['t' => 'Space'], ['t' => 'Strong', 'c' => [['t' => 'Str', 'c' => 'punt']]]]]],
            ]],
        ]]);

        $blocks = (new PandocJsonParser)->parse((string) $json);

        $this->assertSame(Block::LIST, $blocks[0]->type);
        $this->assertSame("- Eerste\n- Tweede punt", $blocks[0]->text);
    }

    public function test_slides_become_one_section_per_slide(): void
    {
        $blocks = (new PandocJsonParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/Knowledge/verlofregeling.pptx.json'));

        $headings = array_map(fn (Block $block): string => $block->text, array_values(array_filter($blocks, fn (Block $block): bool => $block->isHeading())));
        $this->assertSame(['Verlofregeling 2027', 'Vakantiedagen', 'Ziekmelding'], $headings);
        $this->assertContains('Medewerkers krijgen 26 vakantiedagen.', array_map(fn (Block $block): string => $block->text, $blocks));
    }

    public function test_a_spreadsheet_becomes_a_heading_and_a_table_per_sheet(): void
    {
        $blocks = (new PandocJsonParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/Knowledge/tarieven.xlsx.json'));

        $this->assertSame([Block::HEADING, 'Tarieven'], [$blocks[0]->type, $blocks[0]->text]);
        $this->assertSame(Block::TABLE, $blocks[1]->type);
        $this->assertSame(['Functie', 'Uurloon', 'Reiskosten'], $blocks[1]->rows[0]);
        $this->assertSame('Monteur', $blocks[1]->rows[1][0]);
    }

    public function test_csv_becomes_a_table(): void
    {
        $blocks = (new PandocJsonParser)->parse((string) file_get_contents(__DIR__.'/../../Fixtures/Knowledge/tarieven.csv.json'));

        $this->assertSame(Block::TABLE, $blocks[0]->type);
        $this->assertSame(['Planner', '26.00', '0.23'], $blocks[0]->rows[2]);
    }
}
