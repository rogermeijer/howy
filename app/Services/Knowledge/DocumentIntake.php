<?php

namespace App\Services\Knowledge;

use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Enums\ProcessingStatus;
use App\Enums\SourceKind;
use App\Jobs\Knowledge\ProcessDocumentVersion;
use App\Models\Document;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The one way a file enters the knowledge base, uploaded or sent by mail.
 *
 * Every file is known by its sha256, within the account. A file that is
 * already there is never processed again: an upload of it is refused, a mail
 * sharing it is added to it as one more source, which links the document to
 * that conversation as well. A file an administrator rejected stays rejected
 * when it is mailed again; uploading it on purpose is a new decision.
 *
 * Storing answers immediately; everything expensive happens on the queue, and
 * a new version is only searched once it is approved.
 */
class DocumentIntake
{
    /**
     * Files written during the current transaction.
     *
     * @var list<string>
     */
    private array $written = [];

    /**
     * @param  array{title?: string|null, type: string|DocumentType, is_core: bool, language: string|Locale, effective_date?: string|null}  $attributes
     */
    public function newDocument(string $path, string $filename, IntakeSource $source, array $attributes): DocumentVersion
    {
        $sha256 = $this->hash($path);

        return $this->known($sha256, $source) ?? $this->transaction($sha256, $source, function () use ($path, $filename, $source, $attributes, $sha256): DocumentVersion {
            $title = $attributes['title'] ?? null;

            $document = Document::create([
                'title' => filled($title) ? $title : self::titleFrom($filename),
                'type' => $attributes['type'],
                'is_core' => $attributes['is_core'],
                'language' => $attributes['language'],
                'effective_date' => $attributes['effective_date'] ?? null,
                'uploaded_by_user_id' => $source->user?->id,
            ]);

            return $this->storeVersion($document, $path, $filename, $sha256, $source);
        });
    }

    public function newVersion(Document $document, string $path, string $filename, IntakeSource $source): DocumentVersion
    {
        $sha256 = $this->hash($path);

        return $this->known($sha256, $source)
            ?? $this->transaction($sha256, $source, fn (): DocumentVersion => $this->storeVersion($document, $path, $filename, $sha256, $source));
    }

    /**
     * Remove a document, its versions and their files. Everything derived from
     * them goes with the database cascade.
     */
    public function delete(Document $document): void
    {
        $directory = self::directory($document);

        $document->delete();

        Storage::disk((string) config('knowledge.disk'))->deleteDirectory($directory);
    }

    /**
     * Add a source to a version that already exists: once per mail.
     */
    public function addSource(DocumentVersion $version, IntakeSource $source, string $filename): DocumentSource
    {
        $attributes = ['document_id' => $version->document_id, 'kind' => $source->kind, 'user_id' => $source->user?->id, 'filename' => $filename, 'context' => $source->context];

        return $source->email === null
            ? DocumentSource::create(['document_version_id' => $version->id, ...$attributes])
            : DocumentSource::query()->createOrFirst(['document_version_id' => $version->id, 'email_id' => $source->email->id], $attributes);
    }

    /**
     * "personeelshandboek_2026-v3.pdf" becomes "Personeelshandboek 2026 v3".
     */
    public static function titleFrom(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return Str::of($name)->replace(['_', '-'], ' ')->squish()->ucfirst()->limit(255, '')->toString() ?: __('Untitled document');
    }

    /**
     * The version this file already is, if any. Null means: take it in.
     */
    private function known(string $sha256, IntakeSource $source): ?DocumentVersion
    {
        $existing = DocumentVersion::query()->with('document')->where('sha256', $sha256)->first();

        if ($existing === null) {
            return null;
        }

        if ($source->kind === SourceKind::Mail) {
            $this->addSource($existing, $source, $existing->original_filename);

            return $existing;
        }

        // Turned down before; uploading it on purpose is a new decision.
        if ($existing->status === ProcessingStatus::Rejected) {
            $document = $existing->document;
            $existing->delete();

            if (! $document->versions()->exists()) {
                $this->delete($document);
            }

            return null;
        }

        throw ValidationException::withMessages([
            'file' => __('This file has already been uploaded as :title (version :version).', [
                'title' => $existing->document->title,
                'version' => $existing->version_number,
            ]),
        ]);
    }

    private function storeVersion(Document $document, string $path, string $filename, string $sha256, IntakeSource $source): DocumentVersion
    {
        $disk = (string) config('knowledge.disk');
        $number = (int) DocumentVersion::query()->where('document_id', $document->id)->max('version_number') + 1;
        $format = DocumentFormat::detect($filename, mime_content_type($path) ?: null) ?? DocumentFormat::Pdf;

        $stored = Storage::disk($disk)->putFileAs(
            self::directory($document)."/v{$number}",
            new File($path),
            "{$sha256}.{$format->extension()}",
        ) ?: throw new \RuntimeException('The file could not be stored.');

        $this->written[] = $stored;

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $number,
            'original_filename' => Str::limit($filename, 255, ''),
            'mime_type' => $format->mimeType(),
            'size_bytes' => filesize($path) ?: 0,
            'disk' => $disk,
            'path' => $stored,
            'sha256' => $sha256,
            'status' => ProcessingStatus::Queued,
            'uploaded_by_user_id' => $source->user?->id,
        ]);

        $this->addSource($version, $source, $filename);

        ProcessDocumentVersion::dispatch($version->id)->afterCommit();

        return $version;
    }

    /**
     * Run the database work in a transaction; if it fails, remove the files it
     * already wrote so no orphan is left on the disk.
     *
     * @param  callable(): DocumentVersion  $work
     */
    private function transaction(string $sha256, IntakeSource $source, callable $work): DocumentVersion
    {
        $this->written = [];

        try {
            return DB::transaction(fn () => $work());
        } catch (UniqueConstraintViolationException $e) {
            // The same file, taken in at the same moment, won the race.
            $this->deleteWritten();

            return $this->known($sha256, $source) ?? throw $e;
        } catch (Throwable $e) {
            $this->deleteWritten();

            throw $e;
        }
    }

    private function deleteWritten(): void
    {
        Storage::disk((string) config('knowledge.disk'))->delete($this->written);
        $this->written = [];
    }

    private function hash(string $path): string
    {
        return hash_file('sha256', $path) ?: throw new \RuntimeException('The file could not be read.');
    }

    private static function directory(Document $document): string
    {
        return "accounts/{$document->account_id}/documents/{$document->id}";
    }
}
