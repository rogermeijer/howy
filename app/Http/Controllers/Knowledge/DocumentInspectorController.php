<?php

namespace App\Http\Controllers\Knowledge;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Knowledge\DocumentResource;
use App\Models\AiUsageRecord;
use App\Models\Document;
use App\Models\DocumentProcessingStep;
use App\Models\DocumentSection;
use App\Models\DocumentVersion;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeFact;
use App\Models\KnowledgeTopicLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "How is this stored": a document's section tree, and per section its chunks
 * with their context lines, its summary, facts and folders, plus a timeline
 * of how each version was processed and what it cost.
 */
class DocumentInspectorController extends Controller
{
    public function show(Request $request, Document $document): Response
    {
        $document->load(['currentVersion', 'uploadedBy']);

        /** @var DocumentVersion|null $version */
        $version = $request->filled('version')
            ? $document->versions()->whereKey($request->integer('version'))->firstOrFail()
            : $document->currentVersion;

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
                'createdAt' => $item->created_at?->toIso8601String(),
                'isPdf' => $item->isPdf(),
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
     * @return array<int, array<string, mixed>>
     */
    private function steps(DocumentVersion $version): array
    {
        $usage = AiUsageRecord::query()
            ->where('subject_type', 'document_version')
            ->where('subject_id', $version->id)
            ->selectRaw('step, SUM(input_tokens) as input_tokens, SUM(cached_input_tokens) as cached_input_tokens, SUM(output_tokens) as output_tokens, SUM(estimated_cost_micros) as cost_micros, COUNT(*) as calls')
            ->groupBy('step')
            ->get()
            ->keyBy('step');

        return DocumentProcessingStep::query()
            ->where('document_version_id', $version->id)
            ->orderBy('id')
            ->get()
            ->map(function (DocumentProcessingStep $step) use ($usage): array {
                $spent = $usage->get($step->step->value);

                return [
                    'step' => $step->step->value,
                    'status' => $step->status->value,
                    'attempts' => $step->attempts,
                    'startedAt' => $step->started_at?->toIso8601String(),
                    'finishedAt' => $step->finished_at?->toIso8601String(),
                    'error' => $step->error,
                    'meta' => $step->meta ?? (object) [],
                    'usage' => $spent === null ? null : [
                        'calls' => (int) $spent->getAttribute('calls'),
                        'inputTokens' => (int) $spent->getAttribute('input_tokens'),
                        'cachedInputTokens' => (int) $spent->getAttribute('cached_input_tokens'),
                        'outputTokens' => (int) $spent->getAttribute('output_tokens'),
                        'costMicros' => (int) $spent->getAttribute('cost_micros'),
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
