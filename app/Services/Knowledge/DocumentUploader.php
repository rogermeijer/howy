<?php

namespace App\Services\Knowledge;

use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Enums\ProcessingStatus;
use App\Jobs\Knowledge\ProcessDocumentVersion;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores an upload as a new document or a new version, and queues processing.
 *
 * Uploading answers immediately; everything expensive happens on the queue. An
 * identical file (same sha256) is refused within the account, so a document is
 * only ever processed once.
 */
class DocumentUploader
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
    public function create(UploadedFile $file, array $attributes, User $user): Document
    {
        $sha256 = $this->hash($file);
        $this->refuseDuplicate($sha256);

        return $this->transaction($file, function () use ($file, $attributes, $user, $sha256): Document {
            $title = $attributes['title'] ?? null;

            $document = Document::create([
                'title' => filled($title) ? $title : $this->titleFrom($file),
                'type' => $attributes['type'],
                'is_core' => $attributes['is_core'],
                'language' => $attributes['language'],
                'effective_date' => $attributes['effective_date'] ?? null,
                'uploaded_by_user_id' => $user->id,
            ]);

            $this->storeVersion($document, $file, $user, $sha256);

            return $document;
        });
    }

    public function addVersion(Document $document, UploadedFile $file, User $user): DocumentVersion
    {
        $sha256 = $this->hash($file);
        $this->refuseDuplicate($sha256);

        return $this->transaction($file, fn (): DocumentVersion => $this->storeVersion($document, $file, $user, $sha256));
    }

    /**
     * Remove a document, its versions and their files. Everything derived from
     * them goes with the database cascade.
     */
    public function delete(Document $document): void
    {
        $directory = $this->directory($document);

        $document->delete();

        Storage::disk((string) config('knowledge.disk'))->deleteDirectory($directory);
    }

    private function storeVersion(Document $document, UploadedFile $file, User $user, string $sha256): DocumentVersion
    {
        $disk = (string) config('knowledge.disk');
        $number = (int) DocumentVersion::query()->where('document_id', $document->id)->max('version_number') + 1;
        $extension = $this->extension($file);

        $path = Storage::disk($disk)->putFileAs(
            $this->directory($document)."/v{$number}",
            $file,
            "{$sha256}.{$extension}",
        ) ?: throw new \RuntimeException('The upload could not be stored.');

        $this->written[] = $path;

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $number,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $extension === 'pdf' ? DocumentVersion::PDF : DocumentVersion::DOCX,
            'size_bytes' => $file->getSize() ?: 0,
            'disk' => $disk,
            'path' => $path,
            'sha256' => $sha256,
            'status' => ProcessingStatus::Queued,
            'uploaded_by_user_id' => $user->id,
        ]);

        $document->update(['current_version_id' => $version->id]);

        ProcessDocumentVersion::dispatch($version->id)->afterCommit();

        return $version;
    }

    /**
     * Run the database work in a transaction; if it fails, remove the files it
     * already wrote so no orphan is left on the disk.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function transaction(UploadedFile $file, callable $work): mixed
    {
        $this->written = [];

        try {
            return DB::transaction(fn () => $work());
        } catch (UniqueConstraintViolationException) {
            // A concurrent upload of the same file won the race.
            $this->deleteWritten();
            $this->refuseDuplicate($this->hash($file));

            throw ValidationException::withMessages(['file' => __('This file has already been uploaded.')]);
        } catch (Throwable $e) {
            $this->deleteWritten();

            throw $e;
        }
    }

    private function refuseDuplicate(string $sha256): void
    {
        $existing = DocumentVersion::query()->with('document')->where('sha256', $sha256)->first();

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => __('This file has already been uploaded as :title (version :version).', [
                'title' => $existing->document->title,
                'version' => $existing->version_number,
            ]),
        ]);
    }

    private function deleteWritten(): void
    {
        Storage::disk((string) config('knowledge.disk'))->delete($this->written);
        $this->written = [];
    }

    private function hash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath()) ?: throw new \RuntimeException('The upload could not be read.');
    }

    private function directory(Document $document): string
    {
        return "accounts/{$document->account_id}/documents/{$document->id}";
    }

    private function extension(UploadedFile $file): string
    {
        return Str::lower($file->getClientOriginalExtension()) === 'docx' ? 'docx' : 'pdf';
    }

    /**
     * "personeelshandboek_2026-v3.pdf" becomes "Personeelshandboek 2026 v3".
     */
    private function titleFrom(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return Str::of($name)->replace(['_', '-'], ' ')->squish()->ucfirst()->limit(255, '')->toString() ?: __('Untitled document');
    }
}
