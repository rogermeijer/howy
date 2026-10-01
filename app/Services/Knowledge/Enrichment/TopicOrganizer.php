<?php

namespace App\Services\Knowledge\Enrichment;

use App\Enums\KnowledgeOrigin;
use App\Enums\TopicKind;
use App\Enums\TopicReview;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeSummary;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Ai\Agents\TopicAssigner;
use App\Services\Knowledge\Ai\Agents\TopicSplitter;
use App\Services\Knowledge\Ai\Agents\TopicTreeProposer;
use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Files sections into the folder tree, and keeps the tree in shape.
 *
 * - The first time an account has processed documents, the AI proposes the
 *   whole tree (domains › topics › subtopics). Later it only adds subfolders.
 * - A proposed folder that is nearly the same as an existing one (embedding
 *   similarity ≥ topics.dedupe_similarity) is not created: the existing one is
 *   used. This keeps "Verlof" and "Verlofregeling" from both appearing.
 * - A folder that grows past topics.split_threshold direct links gets split
 *   into subfolders.
 * - Everything AI makes is marked new until an admin approves it, and nothing
 *   a person made (origin manual) is ever changed or removed here.
 */
class TopicOrganizer
{
    public function __construct(private readonly AiGateway $ai) {}

    /**
     * @return array<string, int>
     */
    public function organize(DocumentVersion $version): array
    {
        $created = 0;

        if (! KnowledgeTopic::query()->exists()) {
            $created += $this->bootstrap($version);
        }

        $sections = DocumentSection::query()
            ->where('document_version_id', $version->id)
            ->whereHas('summary')
            ->whereDoesntHave('topicLinks')
            ->with('summary')
            ->orderBy('ordinal')
            ->get();

        if ($sections->isEmpty()) {
            return ['topics' => 0, 'new_folders' => $created];
        }

        $result = $this->ai->structured(new TopicAssigner, $this->tree()."\n\n".$this->sectionList($sections), 'topics', $version);

        $named = [];

        /** @var list<array{parent_id: int, name: string, description: string}> $proposals */
        $proposals = $result['new_folders'] ?? [];

        foreach ($proposals as $proposal) {
            $parent = KnowledgeTopic::query()->find($proposal['parent_id']);

            if ($parent === null || $parent->depth >= (int) config('knowledge.topics.max_depth')) {
                continue;
            }

            [$topic, $isNew] = $this->findOrCreate($proposal['name'], $proposal['description'], $parent);
            $named[mb_strtolower($proposal['name'])] = $topic;
            $created += $isNew ? 1 : 0;
        }

        $links = 0;
        $touched = [];

        /** @var list<array{section_id: int, folder_ids: list<int>, new_folder_names: list<string>}> $assignments */
        $assignments = $result['assignments'] ?? [];

        foreach ($assignments as $assignment) {
            $section = $sections->firstWhere('id', $assignment['section_id']);

            if ($section === null) {
                continue;
            }

            $topics = KnowledgeTopic::query()->whereKey($assignment['folder_ids'])->get()->all();

            foreach ($assignment['new_folder_names'] as $name) {
                if (isset($named[mb_strtolower($name)])) {
                    $topics[] = $named[mb_strtolower($name)];
                }
            }

            foreach (array_slice($topics, 0, (int) config('knowledge.topics.max_per_section')) as $topic) {
                KnowledgeTopicLink::query()->firstOrCreate(
                    ['topic_id' => $topic->id, 'linkable_type' => 'document_section', 'linkable_id' => $section->id],
                    ['origin' => KnowledgeOrigin::Ai, 'relevance' => 1],
                );
                $touched[$topic->id] = $topic;
                $links++;
            }
        }

        foreach ($touched as $topic) {
            $this->markStale($topic);

            if ($this->tooFull($topic)) {
                $created += $this->split($topic, $version);
            }
        }

        return ['topics' => $links, 'new_folders' => $created];
    }

    /**
     * Folders and everything above them need a new overview.
     */
    public function markStale(KnowledgeTopic $topic): void
    {
        KnowledgeTopic::query()->whereRaw('path @> ?::ltree', [$topic->path])->update(['summary_stale' => true]);
    }

    /**
     * Propose the first tree from every processed document in the account.
     */
    private function bootstrap(DocumentVersion $version): int
    {
        $documents = Document::query()->with('currentVersion')->get();

        $material = $documents->map(function (Document $document): string {
            $summary = $document->current_version_id === null ? null : KnowledgeSummary::query()
                ->where('summarizable_type', 'document_version')
                ->where('summarizable_id', $document->current_version_id)
                ->value('text');

            $outline = DocumentSection::query()
                ->where('document_version_id', $document->current_version_id)
                ->where('level', '<=', 3)
                ->whereNotNull('heading')
                ->orderBy('ordinal')
                ->pluck('heading_path')
                ->map(fn (string $path): string => '  - '.$path)
                ->implode("\n");

            return "<document title=\"{$document->title}\">\nSummary: ".($summary ?? '–')."\nOutline:\n{$outline}\n</document>";
        })->implode("\n\n");

        $result = $this->ai->structured(new TopicTreeProposer, $material, 'topics', $version);

        $created = 0;

        /** @var list<array{name: string, description: string, topics: list<array{name: string, description: string, subtopics: list<array{name: string, description: string}>}>}> $domains */
        $domains = $result['domains'] ?? [];

        foreach ($domains as $domain) {
            [$root, $isNew] = $this->findOrCreate($domain['name'], $domain['description'], null);
            $created += $isNew ? 1 : 0;

            foreach ($domain['topics'] as $topicData) {
                [$topic, $isNew] = $this->findOrCreate($topicData['name'], $topicData['description'], $root);
                $created += $isNew ? 1 : 0;

                foreach ($topicData['subtopics'] as $sub) {
                    if ($topic->depth < (int) config('knowledge.topics.max_depth')) {
                        $created += $this->findOrCreate($sub['name'], $sub['description'], $topic)[1] ? 1 : 0;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * @return array{0: KnowledgeTopic, 1: bool} the topic, and whether it is new
     */
    public function findOrCreate(string $name, string $description, ?KnowledgeTopic $parent, KnowledgeOrigin $origin = KnowledgeOrigin::Ai): array
    {
        $name = Str::limit(trim($name), 120, '');
        $slug = Str::slug($name);

        $existing = KnowledgeTopic::query()->where('parent_id', $parent?->id)->where('slug', $slug)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $vector = $this->ai->embed([$name.': '.$description], 'topics')[0];

        // Nearly the same folder anywhere in the tree: use that one.
        $similar = KnowledgeTopic::query()
            ->whereNotNull('embedding')
            ->select('*')
            ->selectRaw('1 - (embedding <=> ?::vector) as similarity', ['['.implode(',', $vector).']'])
            ->orderByDesc('similarity')
            ->first();

        if ($similar !== null && (float) $similar->getAttribute('similarity') >= (float) config('knowledge.topics.dedupe_similarity')) {
            return [$similar, false];
        }

        $topic = KnowledgeTopic::create([
            'parent_id' => $parent?->id,
            'kind' => $parent->kind ?? TopicKind::Theme,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'origin' => $origin,
            'review_status' => $origin === KnowledgeOrigin::Manual ? TopicReview::Approved : TopicReview::New,
            'summary_stale' => true,
            'embedding' => $vector,
        ]);

        return [$topic, true];
    }

    /**
     * A folder made by a person: no similarity check (they meant this name),
     * and no embedding call when no AI provider is configured.
     *
     * @return array{0: KnowledgeTopic, 1: bool}
     */
    public function findOrCreateExact(string $name, ?string $description, ?KnowledgeTopic $parent): array
    {
        $name = Str::limit(trim($name), 120, '');
        $existing = KnowledgeTopic::query()->where('parent_id', $parent?->id)->where('slug', Str::slug($name))->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return [KnowledgeTopic::create([
            'parent_id' => $parent?->id,
            'kind' => $parent->kind ?? TopicKind::Theme,
            'name' => $name,
            'description' => $description,
            'origin' => KnowledgeOrigin::Manual,
            'review_status' => TopicReview::Approved,
            'summary_stale' => true,
            'embedding' => $this->ai->enabled() ? $this->ai->embed([$name.': '.$description], 'topics')[0] : null,
        ]), true];
    }

    private function tooFull(KnowledgeTopic $topic): bool
    {
        return $topic->depth < (int) config('knowledge.topics.max_depth')
            && KnowledgeTopicLink::query()->where('topic_id', $topic->id)->where('linkable_type', 'document_section')->count() > (int) config('knowledge.topics.split_threshold');
    }

    /**
     * Divide an overfull folder into subfolders and move its AI-made links.
     */
    private function split(KnowledgeTopic $topic, DocumentVersion $version): int
    {
        $sections = DocumentSection::query()
            ->whereIn('id', KnowledgeTopicLink::query()->select('linkable_id')->where('topic_id', $topic->id)->where('linkable_type', 'document_section'))
            ->with('summary')
            ->get();

        $result = $this->ai->structured(new TopicSplitter, "Folder: {$topic->name} — {$topic->description}\n\n".$this->sectionList($sections), 'topics', $version);

        $created = 0;

        /** @var list<array{name: string, description: string, section_ids: list<int>}> $subfolders */
        $subfolders = $result['subfolders'] ?? [];

        foreach ($subfolders as $subfolder) {
            [$child, $isNew] = $this->findOrCreate($subfolder['name'], $subfolder['description'], $topic);
            $created += $isNew ? 1 : 0;

            if ($child->parent_id !== $topic->id) {
                continue;
            }

            KnowledgeTopicLink::query()
                ->where('topic_id', $topic->id)
                ->where('linkable_type', 'document_section')
                ->whereIn('linkable_id', $subfolder['section_ids'])
                ->where('origin', KnowledgeOrigin::Ai)
                ->update(['topic_id' => $child->id]);

            $this->markStale($child);
        }

        return $created;
    }

    private function tree(): string
    {
        $topics = KnowledgeTopic::query()->orderBy('path')->get();

        if ($topics->isEmpty()) {
            return "<folders>\n(none yet)\n</folders>";
        }

        return "<folders>\n".$topics->map(fn (KnowledgeTopic $topic): string => str_repeat('  ', $topic->depth - 1)."- [{$topic->id}] {$topic->name}".($topic->description ? " — {$topic->description}" : ''))->implode("\n")."\n</folders>";
    }

    /**
     * @param  Collection<int, DocumentSection>  $sections
     */
    private function sectionList(Collection $sections): string
    {
        return "<sections>\n".$sections->map(fn (DocumentSection $section): string => "- [{$section->id}] ".($section->heading_path ?: '(introduction)').': '.($section->summary->text ?? ''))->implode("\n")."\n</sections>";
    }
}
