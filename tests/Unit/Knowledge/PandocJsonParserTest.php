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
}
