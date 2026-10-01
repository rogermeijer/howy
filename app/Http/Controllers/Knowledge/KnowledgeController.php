<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\TopicReview;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Knowledge\DocumentResource;
use App\Models\Document;
use App\Models\KnowledgeTopic;
use App\Services\Knowledge\Topics\TopicStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The knowledge base home: the top-level folders and what changed recently.
 */
class KnowledgeController extends Controller
{
    public function index(Request $request, TopicStats $stats): Response
    {
        $roots = KnowledgeTopic::query()->whereNull('parent_id')->orderBy('name')->get();
        $counts = $stats->sourceCounts($roots);

        $topics = $roots->map(fn (KnowledgeTopic $topic): array => [
            'id' => $topic->id,
            'name' => $topic->name,
            'kind' => $topic->kind->value,
            'isNew' => $topic->review_status === TopicReview::New,
            'updatedAt' => $topic->updated_at?->toIso8601String(),
            'sourcesCount' => $counts[$topic->id] ?? 0,
        ]);

        $recent = Document::query()
            ->with(['currentVersion', 'uploadedBy'])
            ->latest('updated_at')
            ->limit(12)
            ->get();

        return Inertia::render('knowledge/index', [
            'topics' => $topics,
            'recent' => DocumentResource::collection($recent)->resolve(),
            'documentsCount' => Document::query()->count(),
            'newTopicsCount' => KnowledgeTopic::query()->where('review_status', TopicReview::New)->count(),
            'canManage' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }
}
