<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreDocumentVersionRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\Knowledge\DocumentUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function store(StoreDocumentVersionRequest $request, Document $document, DocumentUploader $uploader): RedirectResponse
    {
        $version = $uploader->addVersion($document, $request->file('file'), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :version of :title is uploaded. Only what changed is processed again.', [
                'version' => $version->version_number,
                'title' => $document->title,
            ]),
        ]);

        return back();
    }

    /**
     * The original file, shown inline so a PDF opens at #page=n in the browser.
     */
    public function file(Document $document, DocumentVersion $version): StreamedResponse
    {
        return Storage::disk($version->disk)->response(
            $version->path,
            $version->original_filename,
            ['Content-Type' => $version->mime_type, 'X-Content-Type-Options' => 'nosniff'],
            $version->isPdf() ? 'inline' : 'attachment',
        );
    }
}
