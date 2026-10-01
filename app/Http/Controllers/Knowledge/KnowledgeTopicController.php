<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\FactStatus;
use App\Enums\KnowledgeOrigin;
use App\Enums\TopicKind;
use App\Enums\TopicReview;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Topics\TopicStats;
use App\Services\Knowledge\Topics\TopicTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Folders: browse one (its overview, facts and sources, its subfolders), and
 * for admins, curate the tree.
 */
class KnowledgeTopicController extends Controller
{
    public function __construct(
        private readonly TopicTree $tree,
        private readonly TopicStats $stats,
    ) {}

    /**
     * The whole tree, for reviewing what AI proposed.
     */
    public function index(Request $request): Response
    {
        $topics = KnowledgeTopic::query()->orderBy('path')->get();
        $counts = $this->stats->sourceCounts($topics);

        return Inertia::render('knowledge/topics/index', [
            'topics' => $topics->map(fn (KnowledgeTopic $topic): array => [
                ...$this->folder($topic, $counts),
                'parentId' => $topic->parent_id,
                'depth' => $topic->depth,
                'description' => $topic->description,
                'origin' => $topic->origin->value,
            ])->values(),
            'maxDepth' => (int) config('knowledge.topics.max_depth'),
            'canManage' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }

    public function show(Request $request, KnowledgeTopic $topic): Response
    {
        $includeSubfolders = ! $request->boolean('direct');
        $scope = $includeSubfolders ? KnowledgeTopic::query()->subtreeOf($topic)->get() : collect([$topic]);
        $children = $topic->children()->get();
        $counts = $this->stats->sourceCounts($children);

        $links = KnowledgeTopicLink::query()
            ->with('topic')
            ->whereIn('topic_id', $scope->pluck('id'))
            ->where('linkable_type', 'document_section')
            ->get();

        $current = Document::query()->whereNotNull('current_version_id')->pluck('current_version_id');

        $sections = DocumentSection::query()
            ->with(['summary', 'document', 'version'])
            ->whereKey($links->pluck('linkable_id')->unique())
            ->get()
            ->keyBy('id');

        $currentSections = $sections->filter(fn (DocumentSection $section): bool => $current->contains($section->document_version_id));

        $facts = KnowledgeFact::query()
            ->with(['document', 'section', 'version'])
            ->whereIn('section_id', $sections->keys())
            ->orderBy('status')
            ->orderBy('id')
            ->get()
            // Live facts come from current versions; expired ones may be older.
            ->filter(fn (KnowledgeFact $fact): bool => $fact->status === FactStatus::Expired || $currentSections->has((int) $fact->section_id));

        return Inertia::render('knowledge/topics/show', [
            'topic' => [
                ...$this->folder($topic, $this->stats->sourceCounts(collect([$topic]))),
                'description' => $topic->description,
                'summary' => $topic->summary,
                'summaryStale' => $topic->summary_stale,
                'origin' => $topic->origin->value,
                'depth' => $topic->depth,
                'parentId' => $topic->parent_id,
            ],
            'ancestors' => $topic->ancestors()->map(fn (KnowledgeTopic $ancestor): array => ['id' => $ancestor->id, 'name' => $ancestor->name])->values(),
            'children' => $children->map(fn (KnowledgeTopic $child): array => $this->folder($child, $counts))->values(),
            'facts' => $facts->map(fn (KnowledgeFact $fact): array => [
                'id' => $fact->id,
                'statement' => $fact->statement,
                'status' => $fact->status->value,
                'validFrom' => $fact->valid_from?->toDateString(),
                'validUntil' => $fact->valid_until?->toDateString(),
                'documentId' => $fact->document_id,
                'documentTitle' => $fact->document?->title,
                'versionNumber' => $fact->version?->version_number,
                'sectionId' => $fact->section_id,
                'headingPath' => $fact->section?->heading_path,
                'pageFrom' => $fact->page_from,
            ])->values(),
            'sources' => $links
                ->filter(fn (KnowledgeTopicLink $link): bool => $currentSections->has($link->linkable_id))
                ->unique('linkable_id')
                ->map(function (KnowledgeTopicLink $link) use ($sections, $topic): array {
                    /** @var DocumentSection $section */
                    $section = $sections->get($link->linkable_id);

                    return [
                        'linkId' => $link->id,
                        'origin' => $link->origin->value,
                        'via' => $link->topic_id === $topic->id ? null : $link->topic->name,
                        'sectionId' => $section->id,
                        'documentId' => $section->document_id,
                        'documentTitle' => $section->document->title,
                        'versionNumber' => $section->version->version_number,
                        'headingPath' => $section->heading_path,
                        'pageFrom' => $section->page_from,
                        'summary' => $section->summary?->text,
                    ];
                })->values(),
            'includeSubfolders' => $includeSubfolders,
            'folders' => KnowledgeTopic::query()->orderBy('path')->get(['id', 'name', 'depth', 'path'])->map(fn (KnowledgeTopic $item): array => [
                'id' => $item->id, 'name' => $item->name, 'depth' => $item->depth, 'path' => $item->path,
            ]),
            'maxDepth' => (int) config('knowledge.topics.max_depth'),
            'canManage' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = $request->filled('parent_id') ? KnowledgeTopic::query()->findOrFail($request->integer('parent_id')) : null;
        $topic = $this->tree->create($data['name'], $data['description'] ?? null, $parent);

        return to_route('knowledge.topics.show', $topic);
    }

    public function update(Request $request, KnowledgeTopic $topic): RedirectResponse
    {
        $this->tree->update($topic, $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'kind' => ['sometimes', Rule::enum(TopicKind::class)],
        ]));

        return back();
    }

    public function approve(KnowledgeTopic $topic): RedirectResponse
    {
        $this->tree->approve($topic);

        return back();
    }

    public function approveAll(): RedirectResponse
    {
        $count = KnowledgeTopic::query()->where('review_status', TopicReview::New)->update(['review_status' => TopicReview::Approved]);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} Nothing to approve.|{1} 1 folder approved.|[2,*] :count folders approved.', $count)]);

        return back();
    }

    public function move(Request $request, KnowledgeTopic $topic): RedirectResponse
    {
        $request->validate(['parent_id' => ['nullable', 'integer']]);

        $this->tree->move($topic, $request->filled('parent_id') ? KnowledgeTopic::query()->findOrFail($request->integer('parent_id')) : null);

        return back();
    }

    public function merge(Request $request, KnowledgeTopic $topic): RedirectResponse
    {
        $request->validate(['target_id' => ['required', 'integer']]);
        $target = KnowledgeTopic::query()->findOrFail($request->integer('target_id'));

        $this->tree->merge($topic, $target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':source is merged into :target.', ['source' => $topic->name, 'target' => $target->name])]);

        return to_route('knowledge.topics.show', $target);
    }

    public function destroy(KnowledgeTopic $topic): RedirectResponse
    {
        $parent = $topic->parent;
        $this->tree->delete($topic);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The folder :name is removed.', ['name' => $topic->name])]);

        return $parent === null ? to_route('knowledge') : to_route('knowledge.topics.show', $parent);
    }

    /**
     * File a section in this folder by hand.
     */
    public function link(Request $request, KnowledgeTopic $topic): RedirectResponse
    {
        $request->validate(['section_id' => ['required', 'integer']]);
        $section = DocumentSection::query()->findOrFail($request->integer('section_id'));

        KnowledgeTopicLink::query()->updateOrCreate(
            ['topic_id' => $topic->id, 'linkable_type' => 'document_section', 'linkable_id' => $section->id],
            ['origin' => KnowledgeOrigin::Manual, 'relevance' => 1],
        );

        $topic->update(['summary_stale' => true]);

        return back();
    }

    public function unlink(KnowledgeTopic $topic, KnowledgeTopicLink $link): RedirectResponse
    {
        $link->delete();
        $topic->update(['summary_stale' => true]);

        return back();
    }

    /**
     * @param  array<int, int>  $counts
     * @return array<string, mixed>
     */
    private function folder(KnowledgeTopic $topic, array $counts): array
    {
        return [
            'id' => $topic->id,
            'name' => $topic->name,
            'kind' => $topic->kind->value,
            'isNew' => $topic->review_status === TopicReview::New,
            'updatedAt' => $topic->updated_at?->toIso8601String(),
            'sourcesCount' => $counts[$topic->id] ?? 0,
        ];
    }
}
