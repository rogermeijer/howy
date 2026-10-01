<?php

namespace App\Services\Knowledge\Extraction;

use App\Models\DocumentVersion;
use App\Services\Knowledge\Ai\Agents\ScanReader;
use App\Services\Knowledge\Ai\AiGateway;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document as AiDocument;
use Throwable;

/**
 * Hands pages without a text layer to the LLM: the pages are cut out of the
 * PDF with qpdf (so only those pages leave the application) and read in
 * groups. A group that fails is skipped and its pages stay unread.
 */
class LlmScannedPageReader implements ScannedPageReader
{
    public function __construct(private readonly AiGateway $ai) {}

    public function read(DocumentVersion $version, array $pages): array
    {
        if (! $version->isPdf() || $pages === []) {
            return [];
        }

        $base = (string) tempnam(sys_get_temp_dir(), 'cc-scan-');
        $source = $base.'.pdf';
        file_put_contents($source, Storage::disk($version->disk)->readStream($version->path));

        $result = [];

        try {
            foreach (array_chunk($pages, max(1, (int) config('knowledge.extraction.fallback_pages_per_request'))) as $group) {
                try {
                    $result += $this->readGroup($version, $source, $group);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        } finally {
            @unlink($source);
            @unlink($base);
        }

        return $result;
    }

    /**
     * @param  list<int>  $pages
     * @return array<int, list<Block>>
     */
    private function readGroup(DocumentVersion $version, string $source, array $pages): array
    {
        $base = (string) tempnam(sys_get_temp_dir(), 'cc-pages-');
        $subset = $base.'.pdf';

        try {
            Process::timeout(120)->run([
                (string) config('knowledge.binaries.qpdf'), '--empty', '--pages', $source, implode(',', $pages), '--', $subset,
            ])->throw();

            $structured = $this->ai->structured(
                new ScanReader,
                'The attached PDF holds these pages of the original document, in this order: '.implode(', ', $pages).'.',
                'extraction_fallback',
                $version,
                [AiDocument::fromPath($subset)->withMimeType('application/pdf')],
            );
        } finally {
            @unlink($subset);
            @unlink($base);
        }

        /** @var list<array{page: int, markdown: string}> $read */
        $read = $structured['pages'] ?? [];
        $blocks = [];

        foreach ($read as $page) {
            if (in_array($page['page'], $pages, true) && trim($page['markdown']) !== '') {
                $blocks[$page['page']] = MarkdownBlocks::parse($page['markdown'], $page['page']);
            }
        }

        return $blocks;
    }
}
