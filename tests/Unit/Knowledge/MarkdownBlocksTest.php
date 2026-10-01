<?php

namespace Tests\Unit\Knowledge;

use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\Extraction\MarkdownBlocks;
use PHPUnit\Framework\TestCase;

class MarkdownBlocksTest extends TestCase
{
    public function test_a_transcribed_scan_becomes_blocks_on_its_page(): void
    {
        $blocks = MarkdownBlocks::parse(<<<'MD'
        ## 5 Gereedschap
        Gereedschap wordt na gebruik
        teruggelegd in de bus.

        - Boormachine
        - Lekzoeker

        | Item | Borg |
        |---|---|
        | Sleutelbos | € 50 |
        MD, 7);

        $this->assertSame([Block::HEADING, Block::PARAGRAPH, Block::LIST, Block::TABLE], array_map(fn (Block $block): string => $block->type, $blocks));
        $this->assertSame(2, $blocks[0]->level);
        $this->assertSame('Gereedschap wordt na gebruik teruggelegd in de bus.', $blocks[1]->text);
        $this->assertSame("- Boormachine\n- Lekzoeker", $blocks[2]->text);
        $this->assertSame([['Item', 'Borg'], ['Sleutelbos', '€ 50']], $blocks[3]->rows);
        $this->assertSame([7, 7, 7, 7], array_map(fn (Block $block): ?int => $block->page, $blocks));
    }
}
