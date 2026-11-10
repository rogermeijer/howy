<?php

namespace App\Services\Knowledge\Extraction;

use App\Enums\DocumentFormat;
use App\Exceptions\DocumentExtractionException;
use App\Models\DocumentVersion;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads a document version into blocks with the CLI tools: poppler for PDF,
 * pandoc for the office and text formats; plain text needs no tool. Scanned
 * pages are only flagged here, and an image has no text layer at all: the
 * extraction job hands those to the LLM.
 */
class DocumentExtractor
{
    public function __construct(
        private readonly PdfXmlParser $pdf,
        private readonly PandocJsonParser $pandoc,
    ) {}

    public function extract(DocumentVersion $version): ExtractedDocument
    {
        $format = $version->format();

        // One "page" with nothing on it: the image reader fills it.
        if ($format->isImage()) {
            return new ExtractedDocument([], 1, [1], 'image');
        }

        return $this->withLocalFile($version, fn (string $file): ExtractedDocument => match (true) {
            $format === DocumentFormat::Pdf => $this->extractPdf($file),
            $format === DocumentFormat::Text => $this->extractText($file),
            default => $this->extractWithPandoc($file, (string) $format->pandocReader()),
        });
    }

    private function extractPdf(string $file): ExtractedDocument
    {
        $info = $this->run([$this->binary('pdfinfo'), $file]);
        $pages = preg_match('/^Pages:\s+(\d+)/m', $info->output(), $m) === 1 ? (int) $m[1] : null;
        $max = (int) config('knowledge.upload.max_pages');

        if ($pages !== null && $pages > $max) {
            throw DocumentExtractionException::tooManyPages($pages, $max);
        }

        $xml = $this->run([$this->binary('pdftohtml'), '-xml', '-i', '-stdout', '-q', '-nodrm', $file], encryptedHint: true);

        $parsed = $this->pdf->parse($xml->output(), (int) config('knowledge.extraction.scanned_page_min_chars'));

        return new ExtractedDocument($parsed['blocks'], $pages ?? $parsed['page_count'], $parsed['flagged_pages'], 'poppler');
    }

    private function extractWithPandoc(string $file, string $reader): ExtractedDocument
    {
        $json = $this->run([$this->binary('pandoc'), "--from={$reader}", '--to=json', $file]);

        $blocks = $this->pandoc->parse($json->output());

        if ($blocks === []) {
            throw DocumentExtractionException::empty();
        }

        return new ExtractedDocument($blocks, null, [], 'pandoc');
    }

    /**
     * Plain text has no structure to recover: one paragraph per blank line.
     */
    private function extractText(string $file): ExtractedDocument
    {
        $text = (string) file_get_contents($file);
        $text = mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');

        $blocks = [];

        foreach (preg_split('/\R[ \t]*\R/u', $text) ?: [] as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/u', ' ', $paragraph));

            if ($paragraph !== '') {
                $blocks[] = Block::paragraph($paragraph);
            }
        }

        if ($blocks === []) {
            throw DocumentExtractionException::empty();
        }

        return new ExtractedDocument($blocks, null, [], 'text');
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command, bool $encryptedHint = false): ProcessResult
    {
        $result = Process::timeout((int) config('knowledge.extraction.timeout_seconds'))->run($command);

        if ($result->failed()) {
            $error = trim($result->errorOutput()) ?: trim($result->output());

            if ($encryptedHint && Str::contains(Str::lower($error), ['encrypt', 'password', 'incorrect password'])) {
                throw DocumentExtractionException::encrypted();
            }

            // Tools repeat themselves; the first line says what went wrong.
            throw DocumentExtractionException::unreadable(Str::limit(Str::before($error, "\n"), 200));
        }

        return $result;
    }

    /**
     * The tools need a path on disk; for a remote disk, copy to a temp file.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withLocalFile(DocumentVersion $version, callable $callback): mixed
    {
        $disk = Storage::disk($version->disk);

        if (config("filesystems.disks.{$version->disk}.driver") === 'local') {
            return $callback($disk->path($version->path));
        }

        $base = (string) tempnam(sys_get_temp_dir(), 'cc-doc-');
        $temp = $base.'.'.pathinfo($version->path, PATHINFO_EXTENSION);
        $stream = $disk->readStream($version->path);
        file_put_contents($temp, $stream);

        try {
            return $callback($temp);
        } finally {
            @unlink($temp);
            @unlink($base);
        }
    }

    private function binary(string $name): string
    {
        return (string) config("knowledge.binaries.{$name}", $name);
    }
}
