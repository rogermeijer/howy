<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\FactStatus;
use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\ChooseFactRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\KnowledgeFact;
use App\Services\Knowledge\Review\DocumentReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
    /**
     * Approving acts on the choices made while reviewing. Every conflict
     * needs one: nothing replaces what is known, or is kept against a newer
     * source, without someone choosing it.
     */
    public function approve(Request $request, Document $document, DocumentVersion $version, DocumentReview $review): RedirectResponse
    {
        $proposed = KnowledgeFact::query()->where('document_version_id', $version->id)->where('status', FactStatus::Proposed);
        $conflicts = (clone $proposed)->where('review_verdict', 'conflict')->pluck('review_choice', 'id');

        if ($conflicts->contains(fn (?string $choice): bool => ! in_array($choice, ['new', 'keep'], true))) {
            throw ValidationException::withMessages(['conflicts' => __('Choose for every conflict what holds.')]);
        }

        $keep = array_values(array_map(intval(...), $conflicts->filter(fn (?string $choice): bool => $choice === 'keep')->keys()->all()));
        $omit = array_values((clone $proposed)->where('review_choice', 'omit')->where(fn ($query) => $query->whereNull('review_verdict')->orWhere('review_verdict', '!=', 'conflict'))->pluck('id')->map(intval(...))->all());

        $this->decide(fn () => $review->approve($version, $request->user(), $keep, $omit));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :version of :title is now in the knowledge base.', ['version' => $version->version_number, 'title' => $document->title]),
        ]);

        return to_route('knowledge.documents.show', $document);
    }

    /**
     * A choice about one fact, kept as the reviewer goes, so a review can be
     * picked up later: which fact holds in a conflict, or leaving a new fact
     * out. Nothing is approved by it.
     */
    public function choose(ChooseFactRequest $request, Document $document, DocumentVersion $version, KnowledgeFact $fact): RedirectResponse
    {
        if ($version->isApproved() || $version->status !== ProcessingStatus::Review || $fact->status !== FactStatus::Proposed) {
            abort(409, __('This version is not waiting for approval.'));
        }

        $choice = $request->validated('choice');
        $allowed = $fact->review_verdict === 'conflict' ? ['new', 'keep'] : ['omit'];

        if ($choice !== null && ! in_array($choice, $allowed, true)) {
            throw ValidationException::withMessages(['choice' => __('That choice does not fit this fact.')]);
        }

        $fact->update(['review_choice' => $choice]);

        return back();
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
