<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\FactStatus;
use App\Enums\ProcessingStatus;
use App\Enums\SectionChange;
use App\Enums\TopicReview;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Knowledge\DocumentResource;
use App\Models\Document;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
use App\Services\Knowledge\Enrichment\SectionEnrichment;
use App\Services\Knowledge\Facts\FactSourcePresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "How is this stored": a document's section tree, and per section its chunks
 * with their context lines, its summary, facts and folders, plus a timeline
 * of how each version was processed.
 */
class DocumentInspectorController extends Controller
{
    public function __construct(
        private readonly FactSourcePresenter $sources,
        private readonly SectionEnrichment $enrichment,
    ) {}

    public function show(Request $request, Document $document): Response
    {
        $document->load(['currentVersion', 'latestVersion', 'uploadedBy']);

        // The version on screen is the one that is live, unless a newer one
        // is in progress or waiting for approval.

        /** @var DocumentVersion|null $version */
        $version = $request->filled('version')
            ? $document->versions()->whereKey($request->integer('version'))->firstOrFail()
            : $document->latestVersion;

        $sections = $version === null ? collect() : DocumentSection::query()
            ->where('document_version_id', $version->id)
            ->withCount(['chunks', 'factSources as facts_count'])
            ->orderBy('ordinal')
            ->get();

        $selectedId = $request->integer('section') ?: $sections->first(fn (DocumentSection $section): bool => $section->chunks_count > 0)?->id;

        return Inertia::render('knowledge/documents/show', [
            'document' => (new DocumentResource($document))->resolve(),
            'versions' => $document->versions()->get()->map(fn (DocumentVersion $item): array => [
                'id' => $item->id,
                'number' => $item->version_number,
                'filename' => $item->original_filename,
                'status' => $item->status->value,
                'isApproved' => $item->isApproved(),
                'isCurrent' => $item->id === $document->current_version_id,
                'createdAt' => $item->created_at?->toIso8601String(),
                'isPdf' => $item->isPdf(),
                'isImage' => $item->format()->isImage(),
            ]),
            'version' => $version?->only(['id', 'version_number']),
            'sections' => $sections->map(fn (DocumentSection $section): array => [
                'id' => $section->id,
                'parentId' => $section->parent_id,
                'level' => $section->level,
                'heading' => $section->heading,
                'pageFrom' => $section->page_from,
                'pageTo' => $section->page_to,
                'change' => $section->change_type->value,
                'tokenCount' => $section->token_count,
                'chunksCount' => $section->chunks_count,
                'factsCount' => $section->facts_count,
            ]),
            'section' => fn () => $selectedId ? $this->section($sections->firstWhere('id', $selectedId)) : null,
            'steps' => fn () => $version === null ? [] : $this->steps($version),
            'review' => fn () => $version === null ? null : $this->review($document, $version),
            'canManage' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function section(?DocumentSection $section): ?array
    {
        if ($section === null) {
            return null;
        }

        $section->load(['summary', 'chunks']);

        // What it states, also where the fact is cited from somewhere else.
        $facts = KnowledgeFact::query()
            ->with(['sources.version', 'sources.section', 'relatedFact.document', 'relatedFact.version'])
            ->whereHas('sources', fn ($query) => $query->where('section_id', $section->id))
            ->orderBy('id')
            ->get();

        // Repeats of what the section said before, carried over on approval.
        $repeats = $facts->contains('status', FactStatus::Proposed) && ! $section->version->isApproved()
            ? array_flip(array_map(fn (array $carried): int => $carried['proposed']->id, array_values($this->enrichment->carried($section->version))))
            : [];

        $topics = KnowledgeTopicLink::query()
            ->with('topic')
            ->where('linkable_type', 'document_section')
            ->where('linkable_id', $section->id)
            ->get();

        return [
            'id' => $section->id,
            'heading' => $section->heading,
            'headingPath' => $section->heading_path,
            'pageFrom' => $section->page_from,
            'pageTo' => $section->page_to,
            'change' => $section->change_type->value,
            'summary' => $section->summary?->text,
            'chunks' => $section->chunks->map(fn (KnowledgeChunk $chunk): array => [
                'id' => $chunk->id,
                'kind' => $chunk->kind->value,
                'content' => $chunk->content,
                'context' => $chunk->context,
                'pageFrom' => $chunk->page_from,
                'pageTo' => $chunk->page_to,
                'tokenCount' => $chunk->token_count,
                'embedded' => $chunk->embedding_model !== null,
                'isCurrent' => $chunk->is_current,
            ]),
            'facts' => $facts->map(fn (KnowledgeFact $fact): array => [
                'id' => $fact->id,
                'statement' => $fact->statement,
                'status' => $fact->status->value,
                // Waiting for approval, what counts is how it compares with what is known.
                'verdict' => $fact->status === FactStatus::Proposed ? (isset($repeats[$fact->id]) ? 'carried' : ($fact->review_verdict ?? 'unchecked')) : null,
                'known' => $fact->status === FactStatus::Proposed ? $this->judgedFact($fact)['existing'] : null,
                'explanation' => $fact->status === FactStatus::Proposed ? $this->readable($fact->review_explanation) : null,
                'validFrom' => $fact->valid_from?->toDateString(),
                'validUntil' => $fact->valid_until?->toDateString(),
                'pageFrom' => $fact->page_from,
                'sources' => $this->sources->present($fact),
            ]),
            'topics' => $topics->map(fn (KnowledgeTopicLink $link): array => [
                'id' => $link->topic->id,
                'name' => $link->topic->name,
                'origin' => $link->origin->value,
            ]),
        ];
    }

    /**
     * What approving this version would change, for the administrator who
     * decides: what it replaces, which sections differ, what it adds.
     *
     * @return array<string, mixed>|null
     */
    private function review(Document $document, DocumentVersion $version): ?array
    {
        if ($version->isApproved() || $version->status !== ProcessingStatus::Review) {
            return null;
        }

        $previous = $version->previous();
        $sections = DocumentSection::query()->where('document_version_id', $version->id)->get();
        $matched = $sections->pluck('previous_section_id')->filter()->unique()->count();

        $topics = KnowledgeTopic::query()
            ->whereIn('id', KnowledgeTopicLink::query()
                ->select('topic_id')
                ->where('linkable_type', 'document_section')
                ->whereIn('linkable_id', $sections->pluck('id')))
            ->orderBy('name')
            ->get();

        $sources = DocumentSource::query()->with(['user', 'email'])->where('document_version_id', $version->id)->orderBy('id')->get();

        // What approving does to the facts of the version this one follows.
        $plan = $this->enrichment->handOverPlan($version);
        $repeats = array_map(fn (array $carry): int => $carry['proposed']->id, $plan['carry'] ?? []);
        $expiring = $plan === null ? [] : $this->expiring($plan['expire'], $version, $repeats);
        $staying = $plan === null ? [] : array_values(array_map(fn (array $keep): array => [
            'id' => $keep['fact']->id,
            'statement' => $keep['fact']->statement,
            'source' => $this->sources->one($keep['source']->loadMissing(['version', 'section'])),
        ], array_filter($plan['keep'], fn (array $keep): bool => $keep['elsewhere'])));

        $facts = KnowledgeFact::query()
            ->with(['section:id,heading_path,ordinal', 'relatedFact.document', 'relatedFact.version'])
            ->where('document_version_id', $version->id)
            ->where('status', FactStatus::Proposed)
            ->whereKeyNot($repeats)
            ->orderBy('id')
            ->get();

        return [
            'replaces' => $previous === null || $previous->id !== $document->current_version_id ? null : $previous->version_number,
            'previousVersionId' => $previous?->id,
            // Nothing to compare with: every section is simply there.
            'firstVersion' => $previous === null,
            'uploadedBy' => $version->uploadedBy?->name,
            // Every upload and every mail thread that brought this file.
            'sources' => $sources->map(fn (DocumentSource $source): array => [
                'kind' => $source->kind->value,
                'filename' => $source->filename,
                'user' => $source->user?->name,
                'emailId' => $source->email_id,
                'subject' => $source->email?->subject,
                'from' => $source->email === null ? null : ($source->email->from_name ?? $source->email->from_email),
                'context' => $source->context,
                'createdAt' => $source->created_at?->toIso8601String(),
            ])->values()->all(),
            'summary' => $version->summary?->text,
            'error' => $version->error,
            'sections' => [
                'unchanged' => $sections->where('change_type', SectionChange::Unchanged)->count(),
                'changed' => $sections->where('change_type', SectionChange::Changed)->count(),
                'added' => $sections->where('change_type', SectionChange::Added)->count(),
                'removed' => $previous === null ? 0 : max(0, DocumentSection::query()->where('document_version_id', $previous->id)->count() - $matched),
            ],
            'placement' => $this->placement($version),
            'facts' => [
                'new' => $facts->where('review_verdict', 'new')->count(),
                'duplicate' => $facts->where('review_verdict', 'duplicate')->count(),
                'conflict' => $facts->where('review_verdict', 'conflict')->count(),
                'unchecked' => $facts->whereNull('review_verdict')->count(),
                'expire' => count($expiring),
            ],
            'conflicts' => $facts->where('review_verdict', 'conflict')->map($this->judgedFact(...))->values()->all(),
            'duplicates' => $facts->where('review_verdict', 'duplicate')->take(20)->map($this->judgedFact(...))->values()->all(),
            // What approving adds, in the order of the document.
            'newFacts' => $this->listed($facts->where('review_verdict', 'new')),
            'uncheckedFacts' => $this->listed($facts->whereNull('review_verdict')),
            // What goes on approval, and what stays because something else says it too.
            'expiring' => $expiring,
            'staying' => $staying,
            // Said word for word again in a changed section: carries on as it is.
            'carried' => array_map(fn (array $carry): array => [
                'id' => $carry['fact']->id,
                'statement' => $carry['fact']->statement,
                'sectionId' => $carry['to']->id,
                'heading' => $carry['to']->heading_path,
            ], $plan['carry'] ?? []),
            'folders' => $topics->map(fn (KnowledgeTopic $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
                'isNew' => $topic->review_status === TopicReview::New,
            ])->values()->all(),
        ];
    }

    /**
     * The facts of the previous version that expire on approval, in the order
     * of that version: whether their section is gone or changed, and the new
     * fact that takes their place (the same words, else the same subject in
     * the section it became).
     *
     * @param  list<KnowledgeFact>  $facts
     * @param  list<int>  $repeats  facts of this version that carry an old one on, not new
     * @return list<array{id: int, statement: string, sectionId: int|null, heading: string|null, sectionRemoved: bool, replacedBy: string|null}>
     */
    private function expiring(array $facts, DocumentVersion $version, array $repeats): array
    {
        $sections = DocumentSection::query()->whereKey(array_values(array_unique(array_filter(array_map(fn (KnowledgeFact $fact): ?int => $fact->section_id, $facts)))))->get()->keyBy('id');
        $became = DocumentSection::query()->where('document_version_id', $version->id)->whereNotNull('previous_section_id')->pluck('id', 'previous_section_id');
        $proposed = KnowledgeFact::query()->where('document_version_id', $version->id)->where('status', FactStatus::Proposed)->whereKeyNot($repeats)->get(['id', 'statement', 'subject', 'section_id', 'content_hash']);

        return array_values(collect($facts)
            ->sortBy(fn (KnowledgeFact $fact): array => [$sections->get((int) $fact->section_id)->ordinal ?? PHP_INT_MAX, $fact->id])
            ->map(function (KnowledgeFact $fact) use ($sections, $became, $proposed): array {
                $now = $became[(int) $fact->section_id] ?? null;
                $there = $proposed->where('section_id', $now);
                $replacement = $now === null ? null : ($there->firstWhere('content_hash', $fact->content_hash)
                    ?? ($fact->subject === null ? null : $there->firstWhere('subject', $fact->subject)));

                return [
                    'id' => $fact->id,
                    'statement' => $fact->statement,
                    'sectionId' => $fact->section_id,
                    'heading' => $sections->get((int) $fact->section_id)?->heading_path,
                    'sectionRemoved' => $now === null,
                    'replacedBy' => $replacement?->statement,
                ];
            })
            ->all());
    }

    /**
     * Facts by where they are in the document, with the section they come from.
     *
     * @param  Collection<int, KnowledgeFact>  $facts
     * @return list<array{id: int, statement: string, sectionId: int|null, heading: string|null}>
     */
    private function listed(Collection $facts): array
    {
        return array_values($facts
            ->sortBy(fn (KnowledgeFact $fact): array => [$fact->section->ordinal ?? PHP_INT_MAX, $fact->id])
            ->map(fn (KnowledgeFact $fact): array => [
                'id' => $fact->id,
                'statement' => $fact->statement,
                'sectionId' => $fact->section_id,
                'heading' => $fact->section?->heading_path,
            ])
            ->all());
    }

    /**
     * Where the version was put and why, with the document it points at.
     *
     * @return array<string, mixed>|null
     */
    private function placement(DocumentVersion $version): ?array
    {
        $placement = $version->placement;

        if ($placement === null || $placement['kind'] === 'new') {
            return $placement;
        }

        $documentId = $placement['document_id'] ?? null;

        return [
            ...$placement,
            'title' => $documentId === null ? null : Document::query()->whereKey($documentId)->value('title'),
        ];
    }

    /**
     * A new fact with the known one it duplicates or contradicts, and where
     * that one comes from.
     *
     * @return array<string, mixed>
     */
    private function judgedFact(KnowledgeFact $fact): array
    {
        $known = $fact->relatedFact;
        $email = $known?->source_type === 'email' ? Email::query()->whereKey($known->source_id)->first(['id', 'subject']) : null;

        return [
            'id' => $fact->id,
            'statement' => $fact->statement,
            'explanation' => $this->readable($fact->review_explanation),
            'sectionId' => $fact->section_id,
            'heading' => $fact->section?->heading_path,
            'existing' => $known === null ? null : [
                'statement' => $known->statement,
                'status' => $known->status->value,
                'source' => $email !== null
                    ? ['type' => 'email', 'id' => $email->id, 'title' => (string) $email->subject]
                    : ['type' => 'document', 'id' => $known->document_id, 'title' => $known->documentTitle(), 'versionNumber' => $known->version?->version_number],
            ],
        ];
    }

    /**
     * Explanations written before the check stopped naming facts by id read
     * "fact 182 says": say "the known fact" instead.
     */
    private function readable(?string $explanation): ?string
    {
        return $explanation === null ? null : (string) preg_replace('/\b(?:fact|feit)\s+#?\d+\b/iu', __('the known fact'), $explanation);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function steps(DocumentVersion $version): array
    {
        return DocumentProcessingStep::query()
            ->where('document_version_id', $version->id)
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentProcessingStep $step): array => [
                'step' => $step->step->value,
                'status' => $step->status->value,
                'attempts' => $step->attempts,
                'startedAt' => $step->started_at?->toIso8601String(),
                'finishedAt' => $step->finished_at?->toIso8601String(),
                'error' => $step->error,
                'meta' => $step->meta ?? (object) [],
            ])
            ->values()
            ->all();
    }
}
