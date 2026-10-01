<?php

namespace App\Http\Controllers\Knowledge;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Knowledge\DocumentResource;
use App\Models\Document;
use App\Models\KnowledgeTopic;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The knowledge base home: the top-level folders and what changed recently.
 */
class KnowledgeController extends Controller
{
    public function index(Request $request): Response
    {
        $topics = KnowledgeTopic::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->map(fn (KnowledgeTopic $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
                'kind' => $topic->kind->value,
                'isNew' => $topic->review_status->value === 'new',
                'updatedAt' => $topic->updated_at?->toIso8601String(),
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
            'canManage' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }
}
