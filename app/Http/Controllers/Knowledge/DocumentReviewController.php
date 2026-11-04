<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\ApproveDocumentVersionRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Review\DocumentReview;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use InvalidArgumentException;

/**
 * Approve or reject a processed version before it is searched.
 *
 * Scoped bindings, so another account's version — or another document's —
 * 404s before anything is decided.
 */
class DocumentReviewController extends Controller
{
    public function approve(ApproveDocumentVersionRequest $request, Document $document, DocumentVersion $version, DocumentReview $review): RedirectResponse
    {
        /** @var list<int> $keep */
        $keep = KnowledgeFact::query()
            ->where('document_version_id', $version->id)
            ->whereIn('id', array_map(intval(...), (array) $request->validated('keep_existing', [])))
            ->pluck('id')
            ->map(intval(...))
            ->all();

        $this->decide(fn () => $review->approve($version, $request->user(), $keep));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :version of :title is now in the knowledge base.', ['version' => $version->version_number, 'title' => $document->title]),
        ]);

        return to_route('knowledge.documents.show', $document);
    }

    public function reject(Document $document, DocumentVersion $version, DocumentReview $review): RedirectResponse
    {
        $this->decide(fn () => $review->reject($version));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :version of :title is kept out of the knowledge base.', ['version' => $version->version_number, 'title' => $document->title]),
        ]);

        // A document that was only this version is no longer listed.
        return Document::query()->listed()->whereKey($document->id)->exists()
            ? to_route('knowledge.documents.show', $document)
            : to_route('knowledge.documents.index');
    }

    private function decide(callable $action): void
    {
        try {
            $action();
        } catch (InvalidArgumentException) {
            abort(409, __('This version is not waiting for approval.'));
        }
    }
}
