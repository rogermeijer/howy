<?php

namespace App\Services\Mail;

use App\Enums\AttachmentDecision;
use App\Enums\DocumentFormat;
use App\Enums\DocumentType;
use App\Enums\Locale;
use App\Facades\Tenancy;
use App\Models\DocumentVersion;
use App\Models\Email;
use App\Services\Gmail\GmailClient;
use App\Services\Knowledge\DocumentIntake;
use App\Services\Knowledge\IntakeSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

/**
 * Attachments of a mail on their way into the knowledge base, which holds
 * them as documents like any upload.
 *
 * What cannot be read is set aside without downloading: an unknown format, a
 * file over the limit, an image too small to be more than a logo. The rest is
 * downloaded and hashed. A file the knowledge base already has is never taken
 * in again: this mail becomes one more source of it, so the document links to
 * this conversation too. Only files that are new are put to the classifier,
 * which decides what is knowledge; those go through the document intake and
 * wait for approval like an upload.
 *
 * The decision is written onto the mail's attachment list, so a mail read
 * again does not decide twice.
 *
 * @phpstan-import-type Attachment from Email
 */
class MailAttachments
{
    public function __construct(private readonly DocumentIntake $intake) {}

    /**
     * Screen, download and recognise every attachment not decided yet.
     */
    public function prepare(Email $email): PreparedAttachments
    {
        if (! config('knowledge.attachments.enabled')) {
            return new PreparedAttachments;
        }

        $attachments = $email->attachments ?? [];
        $files = [];

        foreach ($attachments as $index => $attachment) {
            if (($attachment['decision'] ?? null) !== null) {
                continue;
            }

            $screened = $this->screen($attachment);

            if ($screened !== null) {
                $attachments[$index]['decision'] = $screened->value;

                continue;
            }

            try {
                $file = $this->download($email, $attachment);
            } catch (Throwable $e) {
                report($e);
                $attachments[$index] = [...$attachment, 'decision' => AttachmentDecision::Failed->value, 'reason' => Str::limit($e->getMessage(), 200)];

                continue;
            }

            $sha256 = (string) hash_file('sha256', $file);
            $attachments[$index]['sha256'] = $sha256;
            $known = DocumentVersion::query()->where('sha256', $sha256)->first();

            if ($known !== null) {
                $this->intake->addSource($known, IntakeSource::mail($email), $attachment['filename']);
                $attachments[$index] = [...$attachments[$index], 'decision' => AttachmentDecision::Known->value, 'document_version_id' => $known->id];
                @unlink($file);

                continue;
            }

            $files[$index] = $file;
        }

        $this->save($email, $attachments);

        return new PreparedAttachments($files);
    }

    /**
     * The attachments as the classifier sees them: the new ones to decide on,
     * the known ones as context. Addresses in file names are masked.
     */
    public function prompt(Email $email, PreparedAttachments $prepared): ?string
    {
        $lines = [];
        $known = DocumentVersion::query()
            ->with('document')
            ->whereKey(array_filter(array_column($email->attachments ?? [], 'document_version_id')))
            ->get()
            ->keyBy('id');

        foreach ($email->attachments ?? [] as $index => $attachment) {
            $version = $known->get((int) ($attachment['document_version_id'] ?? 0));
            $format = DocumentFormat::detect($attachment['filename'], $attachment['mime_type']);

            if (! $prepared->has($index) && $version === null) {
                continue;
            }

            $attributes = 'id="A'.($index + 1).'" type="'.($format->value ?? 'file').'" size="'.Number::fileSize($attachment['size']).'"'
                .($version !== null ? ' known="'.e($version->document->title).' v'.$version->version_number.'"' : '');

            $lines[] = "  <attachment {$attributes}>".e($this->mask($attachment['filename'])).'</attachment>';
        }

        return $lines === [] ? null : "<attachments>\n".implode("\n", $lines)."\n</attachments>";
    }

    /**
     * Act on the classifier's verdicts: knowledge goes in as a new document,
     * the rest is left out with its reason. An attachment without a verdict
     * stays undecided, for an administrator to add by hand.
     *
     * @param  array<mixed>  $verdicts
     */
    public function apply(Email $email, PreparedAttachments $prepared, array $verdicts, ?string $summary, ?string $language): void
    {
        // In one transaction, so the pipeline of what goes in starts only
        // once the mail says what its attachments became.
        DB::transaction(fn () => $this->decide($email, $prepared, $verdicts, $summary, $language));
    }

    /**
     * @param  array<mixed>  $verdicts
     */
    private function decide(Email $email, PreparedAttachments $prepared, array $verdicts, ?string $summary, ?string $language): void
    {
        $attachments = $email->attachments ?? [];

        foreach ($verdicts as $verdict) {
            $index = is_array($verdict) ? (int) substr((string) ($verdict['id'] ?? ''), 1) - 1 : -1;
            $path = $prepared->path($index);

            if ($path === null || ! isset($attachments[$index])) {
                continue;
            }

            $reason = trim((string) ($verdict['reason'] ?? '')) ?: null;

            if (($verdict['use'] ?? null) !== 'knowledge') {
                $attachments[$index] = [...$attachments[$index], 'decision' => AttachmentDecision::Ignored->value, 'reason' => $reason];

                continue;
            }

            $context = implode(' ', array_filter([$summary, $reason]));
            $attachments[$index] = $this->takeIn($email, $attachments[$index], $path, $context !== '' ? $context : null, $language);
        }

        $this->save($email, $attachments);
    }

    /**
     * An administrator adds an attachment by hand: one that was left out, or
     * came with mail Howy did not read.
     */
    public function addByHand(Email $email, int $index): ?DocumentVersion
    {
        $attachments = $email->attachments ?? [];
        $attachment = $attachments[$index] ?? null;

        if ($attachment === null) {
            return null;
        }

        if (DocumentFormat::detect($attachment['filename'], $attachment['mime_type']) === null) {
            return null;
        }

        $file = $this->download($email, $attachment);

        try {
            DB::transaction(function () use ($email, &$attachments, $index, $attachment, $file): void {
                $attachments[$index] = $this->takeIn($email, $attachment, $file, null, $email->interpretation?->language);
                $this->save($email, $attachments);
            });
        } finally {
            @unlink($file);
        }

        $id = $attachments[$index]['document_version_id'] ?? null;

        return $id === null ? null : DocumentVersion::query()->find($id);
    }

    /**
     * @param  Attachment  $attachment
     * @return Attachment
     */
    private function takeIn(Email $email, array $attachment, string $path, ?string $context, ?string $language): array
    {
        $version = $this->intake->newDocument($path, $attachment['filename'], IntakeSource::mail($email, $context), [
            'type' => DocumentType::Other,
            'is_core' => false,
            'language' => Locale::tryFrom((string) $language) ?? Tenancy::account()->locale,
        ]);

        return [
            ...$attachment,
            'sha256' => $version->sha256,
            'decision' => ($version->wasRecentlyCreated ? AttachmentDecision::New : AttachmentDecision::Known)->value,
            'document_version_id' => $version->id,
        ];
    }

    /**
     * What can be decided without downloading anything.
     *
     * @param  Attachment  $attachment
     */
    private function screen(array $attachment): ?AttachmentDecision
    {
        if (strtolower($attachment['mime_type']) === 'message/rfc822') {
            return AttachmentDecision::Forwarded;
        }

        $format = DocumentFormat::detect($attachment['filename'], $attachment['mime_type']);

        return match (true) {
            $format === null || $attachment['attachment_id'] === null => AttachmentDecision::Unsupported,
            $attachment['size'] > (int) config('knowledge.attachments.max_kilobytes') * 1024 => AttachmentDecision::TooLarge,
            $format->isImage() && $attachment['size'] < (int) config('knowledge.attachments.min_image_bytes') => AttachmentDecision::Decorative,
            default => null,
        };
    }

    /**
     * @param  Attachment  $attachment
     */
    private function download(Email $email, array $attachment): string
    {
        $mailbox = $email->mailbox;

        if ($mailbox === null || $attachment['attachment_id'] === null) {
            throw new \RuntimeException((string) __('The mailbox is no longer connected.'));
        }

        $bytes = GmailClient::for($mailbox)->attachment($email->provider_message_id, $attachment['attachment_id']);

        $base = (string) tempnam(sys_get_temp_dir(), 'cc-attachment-');
        $file = $base.'.'.Str::lower(pathinfo($attachment['filename'], PATHINFO_EXTENSION));
        file_put_contents($file, $bytes);
        @unlink($base);

        return $file;
    }

    /**
     * @param  list<Attachment>  $attachments
     */
    private function save(Email $email, array $attachments): void
    {
        $email->forceFill(['attachments' => $attachments])->save();
    }

    private function mask(string $text): string
    {
        return (string) preg_replace('/[^\s<>@]+@[^\s<>@]+\.[a-z]{2,}/i', '[email]', $text);
    }
}
