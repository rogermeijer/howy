<?php

namespace App\Jobs\Knowledge;

use App\Enums\AttachmentDecision;
use App\Enums\ProcessingStatus;
use App\Enums\ProcessingStep;
use App\Enums\SourceKind;
use App\Models\Document;
use App\Models\DocumentSource;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Services\Knowledge\DocumentIntake;
use App\Services\Knowledge\Placement\DocumentMatcher;
use App\Services\Knowledge\StepRecorder;
use App\Services\Mail\Interpretation\MailReviewState;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Step 1b, for a version that waits for review: hold it against what is
 * known before anything costs a token.
 *
 * - Its text is already there in another file (saved or exported again):
 *   shared by mail, the mail becomes a source of the existing version and
 *   this copy goes; uploaded, the reviewer is told.
 * - An image that is only decoration, shared by mail, is turned down and
 *   remembered, so it is not read again.
 * - It shares sections or its name with a live document: shared by mail, it
 *   becomes that document's next version, so only what changed is processed
 *   and reviewed; uploaded as a new document, the reviewer is told.
 *
 * Then the rest of the pipeline runs.
 */
class PlaceDocumentVersion extends KnowledgeJob
{
    public function handle(DocumentMatcher $matcher, StepRecorder $steps, DocumentIntake $intake, MailReviewState $mail): void
    {
        $version = DocumentVersion::query()->with(['document', 'sources'])->findOrFail($this->versionId);
        $extracted = ExtractDocumentText::load($version);
        $continue = true;

        $steps->run($version, ProcessingStep::Place, function () use ($version, $extracted, $matcher, $intake, $mail, &$continue): array {
            // Only mail that nobody uploaded on purpose is placed for them.
            $fromMail = $version->sources->isNotEmpty() && $version->sources->every(fn (DocumentSource $source): bool => $source->kind === SourceKind::Mail);
            $alone = $version->document->versions()->count() === 1;

            $same = $version->text_sha256 === null ? null : DocumentVersion::query()
                ->with('document')
                ->where('text_sha256', $version->text_sha256)
                ->whereKeyNot($version->id)
                ->orderBy('id')
                ->first();

            if ($same !== null && $fromMail) {
                $this->mergeInto($version, $same, $intake, $mail);
                $continue = false;

                return ['kind' => 'same_content', 'version_id' => $same->id];
            }

            if (! $extracted->worthKeeping && $fromMail) {
                $this->turnDown($version, $mail);
                $continue = false;

                return ['kind' => 'decoration'];
            }

            $placement = ['kind' => 'new'];

            if ($same !== null) {
                $placement = [
                    'kind' => 'same_content',
                    'document_id' => $same->document_id,
                    'version_number' => $same->version_number,
                    'reason' => (string) __('The same text as :title version :version.', ['title' => $same->document->title, 'version' => $same->version_number]),
                ];
            } elseif ($alone && ($match = $matcher->match($version, $extracted)) !== null) {
                if ($fromMail) {
                    $this->moveTo($version, $match->document, $intake);
                }

                $placement = [
                    'kind' => $fromMail ? 'version' : 'similar',
                    'document_id' => $match->document->id,
                    'version_number' => $match->document->currentVersion?->version_number,
                    'score' => round($match->score, 2),
                    'reason' => $match->reason,
                ];
            }

            $version->update(['placement' => $placement]);

            return $placement;
        });

        if ($continue) {
            Bus::chain(ProcessDocumentVersion::afterPlacement($this->versionId))->onQueue((string) config('knowledge.queue'))->dispatch();
        }
    }

    /**
     * The same text is known: every mail that brought this copy becomes a
     * source of the existing version instead, and the copy goes.
     */
    private function mergeInto(DocumentVersion $version, DocumentVersion $existing, DocumentIntake $intake, MailReviewState $mail): void
    {
        $emailIds = $version->sources->pluck('email_id')->filter()->all();

        DB::transaction(function () use ($version, $existing, $intake): void {
            foreach ($version->sources as $source) {
                $duplicate = $source->email_id !== null && DocumentSource::query()->where('document_version_id', $existing->id)->where('email_id', $source->email_id)->exists();

                $duplicate
                    ? $source->delete()
                    : $source->update(['document_version_id' => $existing->id, 'document_id' => $existing->document_id]);
            }

            $this->repoint($version, ['decision' => AttachmentDecision::Known->value, 'document_version_id' => $existing->id]);
            $this->remove($version, $intake);
        });

        foreach (Email::query()->whereKey($emailIds)->get() as $email) {
            $mail->refresh($email);
        }
    }

    /**
     * Decoration: kept as a rejected version, so the same image sent again
     * is recognised and not read again; the mail says why it was left out.
     */
    private function turnDown(DocumentVersion $version, MailReviewState $mail): void
    {
        $reason = (string) __('Only decoration, such as a logo or a banner.');

        Storage::disk($version->disk)->delete(array_values(array_filter([$version->path, $version->extracted_path])));
        $version->update(['status' => ProcessingStatus::Rejected, 'extracted_path' => null, 'error' => $reason]);

        $this->repoint($version, ['decision' => AttachmentDecision::Ignored->value, 'reason' => $reason, 'document_version_id' => null]);

        foreach (Email::query()->whereKey($version->sources->pluck('email_id')->filter()->all())->get() as $email) {
            $mail->refresh($email);
        }
    }

    /**
     * The next version of an existing document: renumbered, its files moved
     * along, the document made for it alone removed.
     */
    private function moveTo(DocumentVersion $version, Document $target, DocumentIntake $intake): void
    {
        $placeholder = $version->document;
        $number = (int) DocumentVersion::query()->where('document_id', $target->id)->max('version_number') + 1;
        $disk = Storage::disk($version->disk);
        $directory = "accounts/{$target->account_id}/documents/{$target->id}/v{$number}";

        $moves = [];

        foreach (['path', 'extracted_path'] as $column) {
            $from = $version->{$column};

            if (is_string($from)) {
                $moves[$column] = $directory.'/'.basename($from);
                $disk->move($from, $moves[$column]);
            }
        }

        DB::transaction(function () use ($version, $target, $number, $moves, $placeholder, $intake): void {
            $version->update(['document_id' => $target->id, 'version_number' => $number, ...$moves]);
            DocumentSource::query()->where('document_version_id', $version->id)->update(['document_id' => $target->id]);

            if (! $placeholder->versions()->exists()) {
                $intake->delete($placeholder);
            }
        });

        $version->setRelation('document', $target);
    }

    /**
     * The mails' attachment lists point at what this version became.
     *
     * @param  array<string, mixed>  $changes
     */
    private function repoint(DocumentVersion $version, array $changes): void
    {
        $emails = Email::query()->whereKey($version->sources->pluck('email_id')->filter()->all())->get();

        foreach ($emails as $email) {
            $email->forceFill(['attachments' => array_map(
                fn (array $attachment): array => ($attachment['document_version_id'] ?? null) === $version->id ? [...$attachment, ...$changes] : $attachment,
                $email->attachments ?? [],
            )])->save();
        }
    }

    private function remove(DocumentVersion $version, DocumentIntake $intake): void
    {
        $document = $version->document;
        Storage::disk($version->disk)->delete(array_values(array_filter([$version->path, $version->extracted_path])));
        $version->delete();

        if (! $document->versions()->exists()) {
            $intake->delete($document);
        }
    }
}
