<?php

namespace Tests\Unit\Knowledge;

use App\Enums\SectionChange;
use App\Models\DocumentSection;
use App\Services\Knowledge\Structure\SectionDraft;
use App\Services\Knowledge\Structure\SectionMatcher;
use App\Services\Knowledge\Structure\StructureBuilder;
use Tests\TestCase;

class SectionMatcherTest extends TestCase
{
    public function test_sections_are_matched_unchanged_changed_moved_or_added(): void
    {
        $previous = collect([
            $this->old(1, '2 Verlof › 2.1 Vakantie', 'Vakantie', '25 dagen per jaar.'),
            $this->old(2, '3 Ziekte', 'Ziekte', 'Bel voor 09:00.'),
            $this->old(3, '4 Onkosten', 'Onkosten', 'Binnen dertig dagen declareren.'),
            $this->old(4, '5 Oud', 'Oud', 'Vervallen regel.'),
        ]);

        $drafts = [
            // Renumbered, same text: still unchanged.
            $this->draft('3 Verlof › 3.1 Vakantie', 'Vakantie', '25 dagen per jaar.'),
            // Same heading, new text.
            $this->draft('3 Ziekte', 'Ziekte', 'Bel voor 08:30.'),
            // Renamed heading, same text.
            $this->draft('5 Declaraties', 'Declaraties', 'Binnen dertig dagen declareren.'),
            // New.
            $this->draft('6 Thuiswerken', 'Thuiswerken', 'Twee dagen per week.'),
        ];

        (new SectionMatcher)->match($drafts, $previous);

        $this->assertSame(
            [[1, SectionChange::Unchanged], [2, SectionChange::Changed], [3, SectionChange::Unchanged], [null, SectionChange::Added]],
            array_map(fn (SectionDraft $draft): array => [$draft->previousSectionId, $draft->change], $drafts),
        );
    }

    private function old(int $id, string $path, string $heading, string $body): DocumentSection
    {
        return (new DocumentSection)->forceFill([
            'id' => $id,
            'heading_path' => $path,
            'heading' => $heading,
            'content_hash' => StructureBuilder::hash($body),
        ]);
    }

    private function draft(string $path, string $heading, string $body): SectionDraft
    {
        return new SectionDraft(0, 1, $heading, $path, null, [], null, null, StructureBuilder::hash($body), 10);
    }
}
