<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreDocumentRequest;
use App\Http\Requests\Knowledge\UpdateDocumentRequest;
use App\Http\Resources\Knowledge\DocumentResource;
use App\Jobs\Knowledge\ProcessDocumentVersion;
use App\Models\Document;
use App\Services\Knowledge\DocumentUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uploaded documents: list, upload, edit and remove. Uploading only stores the
 * file; processing runs on the queue and the list polls for its progress.
 */
class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = Document::query()
            ->listed()
            ->with(['currentVersion', 'latestVersion', 'uploadedBy'])
            ->orderBy('title')
            ->get();

        return Inertia::render('knowledge/documents/index', [
            'documents' => DocumentResource::collection($documents)->resolve(),
            'types' => array_map(fn (DocumentType $type): array => [
                'value' => $type->value,
                'label' => __($type->label()),
            ], DocumentType::cases()),
            'defaultLanguage' => Tenancy::account()->locale->value,
            'filter' => $request->query('filter') === 'review' ? 'review' : null,
            'maxUploadMegabytes' => (int) round((int) config('knowledge.upload.max_kilobytes') / 1024),
            'uploadAccept' => implode(',', [
                ...array_map(fn (string $extension): string => ".{$extension}", DocumentFormat::allExtensions()),
                ...DocumentFormat::allMimeTypes(),
            ]),
            'canManage' => $request->user()->isAdminOf(Tenancy::account()),
        ]);
    }

    public function store(StoreDocumentRequest $request, DocumentUploader $uploader): RedirectResponse
    {
        /** @var array{title?: string|null, type: string, is_core: bool, language: string, effective_date?: string|null} $attributes */
        $attributes = $request->safe()->except('file');

        $document = $uploader->create($request->file('file'), $attributes, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title is uploaded and being processed. It is searched once you approve it.', ['title' => $document->title]),
        ]);

        return to_route('knowledge.documents.index');
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document->update($request->validated());

        return back();
    }

    public function destroy(Document $document, DocumentUploader $uploader): RedirectResponse
    {
        $uploader->delete($document);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title is removed from the knowledge base.', ['title' => $document->title]),
        ]);

        return to_route('knowledge.documents.index');
    }

    /**
     * Run the pipeline again for the newest version, e.g. after a failure.
     */
    public function reprocess(Document $document): RedirectResponse
    {
        if ($document->latestVersion !== null) {
            ProcessDocumentVersion::dispatch($document->latestVersion->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Processing again.')]);

        return back();
    }
}
