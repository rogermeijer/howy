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
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopic;
use App\Models\KnowledgeTopicLink;
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
            ->withCount(['chunks', 'facts'])
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

        $section->load(['summary', 'chunks', 'facts' => fn ($query) => $query->orderBy('id')]);

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
            'facts' => $section->facts->map(fn (KnowledgeFact $fact): array => [
                'id' => $fact->id,
                'statement' => $fact->statement,
                'status' => $fact->status->value,
                'validFrom' => $fact->valid_from?->toDateString(),
                'validUntil' => $fact->valid_until?->toDateString(),
                'pageFrom' => $fact->page_from,
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

        return [
            'replaces' => $previous === null || $previous->id !== $document->current_version_id ? null : $previous->version_number,
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
            'facts' => KnowledgeFact::query()->where('document_version_id', $version->id)->where('status', FactStatus::Proposed)->count(),
            'folders' => $topics->map(fn (KnowledgeTopic $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
                'isNew' => $topic->review_status === TopicReview::New,
            ])->values()->all(),
        ];
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
