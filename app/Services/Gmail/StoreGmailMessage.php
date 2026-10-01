<?php

namespace App\Services\Gmail;

use App\Enums\EmailSource;
use App\Jobs\Mail\InterpretEmail;
use App\Models\Email;
use App\Models\EmailInterpretation;
use App\Models\Mailbox;
use App\Services\Mail\EmailContentExtractor;
use App\Services\Mail\Interpretation\EmailEligibility;

/**
 * Stores one Gmail message as an Email, idempotently.
 *
 * Push, poll and import all funnel through here, so a message that arrives twice
 * (Pub/Sub redelivers, a poll overlaps a push, someone imports what already came
 * in) updates the same row instead of duplicating it. A new message is queued
 * for interpretation.
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

        $email = Email::query()->firstOrNew([
            'mailbox_id' => $mailbox->id,
            'provider_message_id' => $attributes['provider_message_id'],
        ]);

        $email->fill($attributes);
        $email->fill($this->extractor->extract($attributes['body_html'], $attributes['body_text']));

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
        }

        if ($email->received_at !== null
            && ($mailbox->last_message_at === null || $email->received_at->isAfter($mailbox->last_message_at))) {
            $mailbox->forceFill(['last_message_at' => $email->received_at])->save();
        }

        return $email;
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
