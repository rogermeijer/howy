<?php

namespace App\Jobs\Knowledge;

use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Exceptions\DocumentExtractionException;
use App\Models\DocumentVersion;
use App\Services\Knowledge\Extraction\DocumentExtractor;
use App\Services\Knowledge\Extraction\ExtractedDocument;
use App\Services\Knowledge\Extraction\ScannedPageReader;
use App\Services\Knowledge\StepRecorder;
use Illuminate\Support\Facades\Storage;

/**
 * Step 1: read the file into blocks and store them as JSON next to it. Pages
 * without a text layer go to the LLM fallback.
 */
class ExtractDocumentText extends KnowledgeJob
{
    public function handle(DocumentExtractor $extractor, ScannedPageReader $scans, StepRecorder $steps): void
    {
        $version = DocumentVersion::query()->findOrFail($this->versionId);
        $version->update(['status' => ProcessingStatus::Extracting]);

        try {
            $steps->run($version, ProcessingStep::Extract, fn (): array => $this->extract($version, $extractor, $scans));
        } catch (DocumentExtractionException $e) {
            // Retrying will not make a protected, oversized or empty file readable.
            $this->fail($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extract(DocumentVersion $version, DocumentExtractor $extractor, ScannedPageReader $scans): array
    {
        $extracted = $extractor->extract($version);

        $read = [];

        if ($extracted->flaggedPages !== []) {
            $byPage = $scans->read($version, $extracted->flaggedPages);
            $read = array_keys($byPage);
            $extracted = $extracted->withPages($byPage, $extracted->method.'+llm');
        }

        if ($extracted->characterCount() === 0) {
            throw DocumentExtractionException::empty();
        }

        $path = preg_replace('/\.[a-z]+$/', '', $version->path).'.extracted.json';
        Storage::disk($version->disk)->put($path, $extracted->toJson());

        $version->update(['extracted_path' => $path, 'page_count' => $extracted->pageCount]);

        return [
            'method' => $extracted->method,
            'blocks' => count($extracted->blocks),
            'pages' => $extracted->pageCount,
            'scanned_pages' => $read,
            'unread_pages' => $extracted->flaggedPages,
        ];
    }

    public static function load(DocumentVersion $version): ExtractedDocument
    {
        return ExtractedDocument::fromJson((string) Storage::disk($version->disk)->get((string) $version->extracted_path));
    }
}
