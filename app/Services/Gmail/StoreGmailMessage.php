<?php

namespace App\Services\Gmail;

use App\Enums\EmailSource;
use App\Enums\InterpretationStatus;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Services\Mail\EmailContentExtractor;
use App\Services\Mail\Interpretation\EmailEligibility;
use App\Services\Mail\MailFingerprints;

/**
 * Stores one Gmail message as an Email, idempotently.
 *
 * Push, poll and import all funnel through here, so a message that arrives twice
 * (Pub/Sub redelivers, a poll overlaps a push, someone imports what already came
 * in) updates the same row instead of duplicating it. A new message is queued
 * for interpretation.
 *
 * @phpstan-import-type Forwarded from GmailMessageParser
 * @phpstan-import-type Quote from Email
 * @phpstan-import-type Attachment from Email
 */
class StoreGmailMessage
{
    public function __construct(
        private readonly GmailMessageParser $parser,
        private readonly EmailContentExtractor $extractor,
        private readonly EmailEligibility $eligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $message  a messages.get resource with format=full
     */
    public function store(Mailbox $mailbox, array $message, EmailSource $source, ?int $importedBy = null): Email
    {
        $attributes = $this->parser->parse($message);
        $forwarded = $attributes['forwarded'];
        unset($attributes['forwarded']);

        $email = Email::query()->firstOrNew([
            'mailbox_id' => $mailbox->id,
            'provider_message_id' => $attributes['provider_message_id'],
        ]);

        $decided = $email->attachments ?? [];
        $email->fill($attributes);
        $email->attachments = $this->keepDecisions($attributes['attachments'], $decided);

        $content = $this->extractor->extract($attributes['body_html'], $attributes['body_text']);
        $email->fill([
            ...$content,
            // A mail forwarded as an attachment is history like a quote.
            'quotes' => [...$content['quotes'], ...$this->forwardedQuotes($forwarded)],
            'content_hash' => $content['content_text'] === null ? null : MailFingerprints::hash($content['content_text']),
        ]);

        // The inbox groups by thread, so every email needs one. Gmail always
        // sends a threadId; a message without one is a thread of its own.
        $email->provider_thread_id ??= $attributes['provider_message_id'];

        // Keep how a message first arrived: a later import of a pushed message
        // should not rewrite its history.
        if (! $email->exists) {
            $email->source = $source;
            $email->imported_by_user_id = $source === EmailSource::Import ? $importedBy : null;
        }

        $email->save();

        // New mail gets read and acted on, once: answered when written to the
        // mailbox, listened to when the mailbox is only copied.
        $mode = $email->wasRecentlyCreated ? $this->eligibility->mode($email, $mailbox) : null;

        if ($mode !== null) {
            EmailInterpretation::query()->firstOrCreate(['email_id' => $email->id], ['mode' => $mode]);
            InterpretEmail::dispatch($email->id);
        } elseif ($email->wasRecentlyCreated && $this->eligibility->refused($email, $mailbox)) {
            EmailInterpretation::query()->firstOrCreate(['email_id' => $email->id], [
                'mode' => $this->eligibility->role($email, $mailbox),
                'status' => InterpretationStatus::Skipped,
                'error' => __('The sender is outside the rules of this mailbox.'),
                'processed_at' => now(),
            ]);
        }

        if ($email->received_at !== null
            && ($mailbox->last_message_at === null || $email->received_at->isAfter($mailbox->last_message_at))) {
            $mailbox->forceFill(['last_message_at' => $email->received_at])->save();
        }

        return $email;
    }

    /**
     * A message stored again keeps what was decided about its attachments.
     *
     * @param  list<Attachment>  $attachments
     * @param  list<Attachment>  $decided
     * @return list<Attachment>
     */
    private function keepDecisions(array $attachments, array $decided): array
    {
        return array_map(function (array $attachment) use ($decided): array {
            foreach ($decided as $earlier) {
                $same = ($attachment['part_id'] ?? null) !== null
                    ? ($earlier['part_id'] ?? null) === $attachment['part_id']
                    : $earlier['filename'] === $attachment['filename'] && $earlier['size'] === $attachment['size'];

                if ($same) {
                    return [...$attachment, ...array_intersect_key($earlier, array_flip(['sha256', 'decision', 'reason', 'document_version_id']))];
                }
            }

            return $attachment;
        }, $attachments);
    }

    /**
     * Each forwarded mail as a quote, with the history it carries itself.
     *
     * @param  list<Forwarded>  $forwarded
     * @return list<Quote>
     */
    private function forwardedQuotes(array $forwarded): array
    {
        $quotes = [];

        foreach ($forwarded as $mail) {
            $content = $this->extractor->extract($mail['body_html'], $mail['body_text']);

            if ($content['content_text'] !== null) {
                $quotes[] = [
                    'name' => $mail['from_name'],
                    'email' => $mail['from_email'],
                    'date' => $mail['date'],
                    'date_text' => null,
                    'text' => $content['content_text'],
                    'message_id' => $mail['message_id'],
                    'forwarded' => true,
                ];
            }

            $quotes = [...$quotes, ...$content['quotes']];
        }

        return $quotes;
    }

    /**
     * Which of these Gmail ids are already stored for the mailbox.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function existing(Mailbox $mailbox, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<string> */
        return Email::query()
            ->where('mailbox_id', $mailbox->id)
            ->whereIn('provider_message_id', $ids)
            ->pluck('provider_message_id')
            ->all();
    }
}
