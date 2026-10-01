<?php

namespace Tests\Unit\Knowledge;

use App\Enums\ChunkKind;
use App\Services\Knowledge\Chunking\StructuralChunker;
use App\Services\Knowledge\Extraction\Block;
use App\Services\Knowledge\Extraction\ExtractedDocument;
use App\Services\Knowledge\Structure\SectionDraft;
use App\Services\Knowledge\Structure\StructureBuilder;
use App\Services\Knowledge\TokenEstimator;
use Tests\TestCase;

class StructureAndChunkingTest extends TestCase
{
    public function test_sections_form_a_tree_with_heading_paths_and_pages(): void
    {
        $drafts = (new StructureBuilder(new TokenEstimator))->build(new ExtractedDocument([
            Block::paragraph('Voorwoord zonder kop.', 1),
            Block::heading('2 Verlof', 1, 1),
            Block::heading('2.1 Vakantie', 2, 1),
            Block::paragraph('25 dagen per jaar.', 2),
            Block::heading('3 Ziekte', 1, 3),
            Block::paragraph('Bel voor 09:00.', 3),
        ]));

        $this->assertSame(
            [
                [null, '', null],
                ['2 Verlof', '2 Verlof', null],
                ['2.1 Vakantie', '2 Verlof › 2.1 Vakantie', 1],
                ['3 Ziekte', '3 Ziekte', null],
            ],
            array_map(fn (SectionDraft $draft): array => [$draft->heading, $draft->headingPath, $draft->parentOrdinal], $drafts),
        );
        $this->assertSame([1, 2], [$drafts[2]->pageFrom, $drafts[2]->pageTo]);
        $this->assertSame(StructureBuilder::hash('25 dagen   per jaar.'), $drafts[2]->contentHash);
    }

    public function test_paragraphs_are_packed_and_tables_get_their_own_chunk(): void
    {
        $chunks = $this->chunker()->chunk($this->section([
            Block::paragraph('Eerste alinea.', 1),
            Block::paragraph('Tweede alinea.', 1),
            Block::table([['Jaren', 'Dagen'], ['5', '1']], 2),
            Block::paragraph('Na de tabel.', 2),
        ]));

        $this->assertSame([ChunkKind::Text, ChunkKind::Table, ChunkKind::Text], array_map(fn ($chunk) => $chunk->kind, $chunks));
        $this->assertSame("Eerste alinea.\n\nTweede alinea.", $chunks[0]->content);
        $this->assertSame(2, $chunks[1]->pageFrom);
    }

    public function test_a_long_paragraph_is_split_at_sentences_within_the_maximum(): void
    {
        config(['knowledge.chunking.target_tokens' => 60, 'knowledge.chunking.max_tokens' => 100, 'knowledge.chunking.overlap_tokens' => 20]);

        $sentence = 'Medewerkers melden zich bij ziekte voor negen uur telefonisch bij hun leidinggevende.';
        $chunks = $this->chunker()->chunk($this->section([Block::paragraph(implode(' ', array_fill(0, 12, $sentence)), 4)]));

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, $chunk->tokenCount);
            $this->assertStringEndsWith('.', $chunk->content);
            $this->assertSame(4, $chunk->pageFrom);
        }
    }

    public function test_a_large_table_is_split_by_rows_repeating_the_header(): void
    {
        config(['knowledge.chunking.target_tokens' => 40, 'knowledge.chunking.max_tokens' => 60]);

        $rows = [['Functie', 'Schaal', 'Toelichting']];
        for ($i = 1; $i <= 20; $i++) {
            $rows[] = ["Monteur niveau {$i}", "Schaal {$i}", 'Inclusief storingsdienst in het weekend'];
        }

        $chunks = $this->chunker()->chunk($this->section([Block::table($rows, 3)]));

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertSame(ChunkKind::Table, $chunk->kind);
            $this->assertStringStartsWith('| Functie | Schaal | Toelichting |', $chunk->content);
        }
    }

    private function chunker(): StructuralChunker
    {
        return new StructuralChunker(new TokenEstimator);
    }

    /**
     * @param  list<Block>  $blocks
     */
    private function section(array $blocks): SectionDraft
    {
        return new SectionDraft(0, 1, 'Kop', 'Kop', null, $blocks, 1, 1, 'hash', 100);
    }
}
