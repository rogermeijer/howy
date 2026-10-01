<?php

namespace App\Services\Knowledge\Topics;

use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use Illuminate\Support\Collection;

class TopicStats
{
    /**
     * Distinct current sections filed in each topic or below it, by topic id.
     *
     * @param  Collection<int, KnowledgeTopic>  $topics
     * @return array<int, int>
     */
    public function sourceCounts(Collection $topics): array
    {
        $counts = [];

        foreach ($topics as $topic) {
            $counts[$topic->id] = KnowledgeTopicLink::query()
                ->whereIn('topic_id', KnowledgeTopic::query()->subtreeOf($topic)->select('id'))
                ->where('linkable_type', 'document_section')
                ->whereIn('linkable_id', DocumentSection::query()->select('document_sections.id')->whereIn('document_version_id', Document::query()->select('current_version_id')))
                ->distinct()
                ->count('linkable_id');
        }

        return $counts;
    }
}
