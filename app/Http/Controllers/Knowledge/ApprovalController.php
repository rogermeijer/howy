<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\EmailInterpretation;
use App\Services\Knowledge\Review\ApprovalQueue;
use App\Services\Mail\Interpretation\InterpretationPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one place where an administrator approves what reaches the knowledge
 * base: processed documents, uploaded or mailed, and the statements mail
 * would add or change. A document is judged on its own page, where its
 * changes and conflicts are; a statement is decided right here.
 */
class ApprovalController extends Controller
{
    public function index(ApprovalQueue $queue, InterpretationPresenter $presenter): Response
    {
        $versions = $queue->documents()
            ->with(['document.currentVersion', 'sources.user', 'sources.email'])
            ->latest('updated_at')
            ->get();

        // What a version looks like, to say so next to it.
        $alike = Document::query()
            ->whereKey($versions->map(fn (DocumentVersion $version): ?int => $version->placement['document_id'] ?? null)->filter()->unique()->values())
            ->pluck('title', 'id');

        $interpretations = $queue->mails()
            ->with('email')
            ->get()
            ->sortByDesc(fn (EmailInterpretation $interpretation): int => $interpretation->email->received_at?->getTimestamp() ?? 0)
            ->values();

        return Inertia::render('knowledge/approvals', [
            'documents' => $versions->map(fn (DocumentVersion $version): array => [
                'documentId' => $version->document_id,
                'versionId' => $version->id,
                'title' => $version->document->title,
                'filename' => $version->original_filename,
                'versionNumber' => $version->version_number,
                'replaces' => $version->document->currentVersion?->version_number,
                'hint' => in_array($version->placement['kind'] ?? null, ['similar', 'same_content'], true) ? [
                    'kind' => $version->placement['kind'] ?? null,
                    'title' => $alike[$version->placement['document_id'] ?? 0] ?? null,
                ] : null,
                'sources' => $version->sources->map(fn (DocumentSource $source): array => [
                    'kind' => $source->kind->value,
                    'user' => $source->user?->name,
                    'emailId' => $source->email_id,
                    'subject' => $source->email?->subject,
                    'from' => $source->email === null ? null : ($source->email->from_name ?? $source->email->from_email),
                ])->values()->all(),
                'error' => $version->error,
                'processedAt' => ($version->processed_at ?? $version->updated_at)?->toIso8601String(),
            ])->values()->all(),
            'mails' => $interpretations->map(fn (EmailInterpretation $interpretation): array => [
                'emailId' => $interpretation->email_id,
                'subject' => $interpretation->email->subject,
                'from' => $interpretation->email->from_name ?? $interpretation->email->from_email,
                'receivedAt' => $interpretation->email->received_at?->toIso8601String(),
                'summary' => $interpretation->summary,
                'statements' => array_values(array_filter(
                    $presenter->statements($interpretation),
                    fn (array $statement): bool => $statement['review'] === 'pending',
                )),
            ])->values()->all(),
        ]);
    }
}
