<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\DocumentType;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Services\Knowledge\Search\FactHit;
use App\Services\Knowledge\Search\KnowledgeSearch;
use App\Services\Knowledge\Search\Passage;
use App\Services\Knowledge\Search\SearchQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Search the knowledge base the way an answer would be built from it:
 * passages with their exact source, plus the facts that match.
 */
class KnowledgeSearchController extends Controller
{
    public function index(Request $request, KnowledgeSearch $search): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'topic' => ['nullable', 'integer'],
        ]);

        $text = trim((string) ($validated['q'] ?? ''));
        $type = isset($validated['type']) ? DocumentType::from($validated['type']) : null;

        $result = $text === '' ? null : $search->search(new SearchQuery(
            text: $text,
            documentTypes: $type ? [$type] : [],
            topicId: isset($validated['topic']) ? (int) $validated['topic'] : null,
        ));

        return Inertia::render('knowledge/search', [
            'query' => ['q' => $text, 'type' => $type?->value],
            'types' => array_map(fn (DocumentType $item): array => ['value' => $item->value, 'label' => __($item->label())], DocumentType::cases()),
            'result' => $result === null ? null : [
                'tokenCount' => $result->tokenCount,
                'usedVectors' => $result->usedVectors,
                'passages' => array_map(fn (Passage $passage): array => [
                    'documentId' => $passage->documentId,
                    'versionId' => $passage->versionId,
                    'versionNumber' => $passage->versionNumber,
                    'documentTitle' => $passage->documentTitle,
                    'sectionId' => $passage->sectionId,
                    'headingPath' => $passage->headingPath,
                    'pageFrom' => $passage->pageFrom,
                    'pageTo' => $passage->pageTo,
                    'text' => $passage->text,
                    'score' => round($passage->score, 4),
                    'tokenCount' => $passage->tokenCount,
                    'ranks' => $passage->ranks,
                    'wholeSection' => $passage->wholeSection,
                ], $result->passages),
                'facts' => array_map(fn (FactHit $fact): array => [
                    'id' => $fact->id,
                    'statement' => $fact->statement,
                    'status' => $fact->status->value,
                    'validFrom' => $fact->validFrom,
                    'documentId' => $fact->documentId,
                    'documentTitle' => $fact->documentTitle,
                    'sectionId' => $fact->sectionId,
                    'headingPath' => $fact->headingPath,
                    'pageFrom' => $fact->pageFrom,
                ], $result->facts),
            ],
            'canDebug' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }
}
