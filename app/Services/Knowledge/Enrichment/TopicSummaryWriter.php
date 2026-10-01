<?php

namespace App\Services\Knowledge\Enrichment;

use App\Enums\FactStatus;
use App\Models\DocumentSection;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Ai\Agents\TopicSummarizer;
use App\Services\Knowledge\Ai\AiGateway;

/**
 * Rewrites the overviews of folders whose content changed, deepest first, so
 * a folder's overview is built from its subfolders' fresh overviews. Each
 * claim in an overview cites a numbered source.
 */
class TopicSummaryWriter
{
    private const int MAX_SOURCES = 40;

    public function __construct(private readonly AiGateway $ai) {}

    public function refreshStale(): int
    {
        $refreshed = 0;

        $topics = KnowledgeTopic::query()->where('summary_stale', true)->orderByDesc('depth')->get();

        foreach ($topics as $topic) {
            $material = $this->material($topic);

            $summary = null;

            if ($material !== '') {
                $summary = trim((string) ($this->ai->structured(
                    new TopicSummarizer,
                    "Folder: {$topic->name}".($topic->description ? " — {$topic->description}" : '')."\n\n{$material}",
                    'topic_summary',
                    $topic,
                )['summary'] ?? '')) ?: null;
            }

            $topic->update(['summary' => $summary, 'summary_stale' => false]);
            $refreshed++;
        }

        return $refreshed;
    }

    private function material(KnowledgeTopic $topic): string
    {
        $sources = [];

        foreach ($topic->children()->whereNotNull('summary')->get() as $child) {
            $sources[] = "Subfolder {$child->name}: {$child->summary}";
        }

        $sectionIds = KnowledgeTopicLink::query()
            ->where('topic_id', $topic->id)
            ->where('linkable_type', 'document_section')
            ->pluck('linkable_id');

        $sections = DocumentSection::query()->with(['summary', 'document'])->whereKey($sectionIds)->get();

        foreach ($sections as $section) {
            $facts = KnowledgeFact::query()
                ->where('section_id', $section->id)
                ->where('status', '!=', FactStatus::Expired)
                ->pluck('statement')
                ->map(fn (string $statement): string => '  - '.$statement)
                ->implode("\n");

            $sources[] = "{$section->document->title} › {$section->heading_path}: ".($section->summary->text ?? '').($facts !== '' ? "\n{$facts}" : '');
        }

        $sources = array_slice($sources, 0, self::MAX_SOURCES);

        return implode("\n", array_map(fn (string $source, int $index): string => '['.($index + 1).'] '.$source, $sources, array_keys($sources)));
    }
}
