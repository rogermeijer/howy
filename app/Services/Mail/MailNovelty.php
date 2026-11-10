<?php

namespace App\Services\Mail;

use App\Enums\FingerprintKind;
use App\Enums\InterpretationStatus;
use App\Models\Email;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Splits a mail into what is new and what Howy already read.
 *
 * A thread that continues quotes everything before it; a forward carries the
 * forwarded mail along; a mail client that does not mark its quotes leaves
 * old paragraphs in the new text. Each of those is recognised by its
 * fingerprint, by the Message-ID it was forwarded with, or as the stored
 * message of this thread it repeats, and is left out as a source.
 *
 * @phpstan-import-type Quote from Email
 */
class MailNovelty
{
    public function __construct(private readonly MailFingerprints $fingerprints) {}

    public function read(Email $email): MailReading
    {
        $body = (string) ($email->content_text ?? $email->body_text ?? '');
        $paragraphs = $this->fingerprints->paragraphs($body);
        $knownParagraphs = $this->fingerprints->known(FingerprintKind::Paragraph, array_keys($paragraphs), $email);
        $knownFrom = $knownParagraphs->pluck('email_id')->all();

        $text = $this->withoutKnown($body, array_values($knownParagraphs->keys()->all()));

        $thread = $this->thread($email);
        $known = [];
        $unseen = [];

        foreach ($email->quotes ?? [] as $quote) {
            $from = $this->readIn($quote, $email, $thread);

            if ($from === null) {
                $unseen[] = $quote;

                continue;
            }

            $known[] = $quote;
            $knownFrom = [...$knownFrom, ...$from];
        }

        return new MailReading(
            text: $text,
            remaining: mb_strlen(MailFingerprints::normalize(str_replace('[…]', '', $text))),
            knownParagraphs: $knownParagraphs->count(),
            known: $known,
            unseen: $unseen,
            knownFrom: array_values(array_unique(array_map(intval(...), $knownFrom))),
        );
    }

    /**
     * The stored message of this conversation that a quote repeats, if any:
     * same sender, and its own text matches the start of the quote.
     *
     * @param  Quote  $quote
     * @param  Collection<int, Email>  $thread
     */
    public function quotedEmail(array $quote, Email $quoting, Collection $thread): ?Email
    {
        $needle = Str::limit(Str::squish($quote['text']), 80, '');

        return $thread->first(fn (Email $candidate): bool => ! $candidate->is($quoting)
            && ($quote['email'] === null || $quote['email'] === $candidate->from_email)
            && $needle !== ''
            && str_starts_with(Str::squish($candidate->content_text ?? $candidate->body_text ?? ''), $needle));
    }

    /**
     * Where a quoted or forwarded message was read before, or null when it
     * was not.
     *
     * @param  Quote  $quote
     * @param  Collection<int, Email>  $thread
     * @return list<int>|null
     */
    private function readIn(array $quote, Email $email, Collection $thread): ?array
    {
        // Forwarded as an attachment: it names the message it is.
        $messageId = $quote['message_id'] ?? null;

        if ($messageId !== null) {
            $original = Email::query()
                ->where('message_id_header', $messageId)
                ->whereKeyNot($email->id)
                ->whereHas('interpretation', fn ($query) => $query->where('status', InterpretationStatus::Done))
                ->value('id');

            if ($original !== null) {
                return [(int) $original];
            }
        }

        $message = $this->fingerprints->known(FingerprintKind::Message, [MailFingerprints::hash($quote['text'])], $email)->first();

        if ($message !== null) {
            return [$message->email_id];
        }

        // Paragraph by paragraph: most of it read before is read.
        $paragraphs = $this->fingerprints->paragraphs($quote['text']);
        $total = array_sum(array_map(fn (string $paragraph): int => mb_strlen(MailFingerprints::normalize($paragraph)), $paragraphs));

        if ($total === 0) {
            // Too short to learn anything from: "Thanks!" quoted back.
            return [];
        }

        $known = $this->fingerprints->known(FingerprintKind::Paragraph, array_keys($paragraphs), $email);
        $covered = array_sum(array_map(fn (string $hash): int => mb_strlen(MailFingerprints::normalize($paragraphs[$hash])), $known->keys()->all()));

        if ($covered / $total >= (float) config('knowledge.mail.quote_known_ratio')) {
            return array_values(array_unique($known->pluck('email_id')->map(intval(...))->all()));
        }

        $stored = $this->quotedEmail($quote, $email, $thread);

        return $stored?->interpretation?->status === InterpretationStatus::Done ? [$stored->id] : null;
    }

    /**
     * The mail's text with every paragraph read before replaced by a marker,
     * so the reader sees what is new and where something was left out.
     *
     * @param  list<string>  $knownHashes
     */
    private function withoutKnown(string $body, array $knownHashes): string
    {
        if ($knownHashes === []) {
            return trim($body);
        }

        $known = array_flip($knownHashes);
        $kept = [];

        foreach (preg_split('/\n[ \t]*\n/u', str_replace("\r\n", "\n", $body)) ?: [] as $paragraph) {
            $isKnown = isset($known[MailFingerprints::hash(trim((string) preg_replace('/^[ \t]*(?:>[ \t]?)+/mu', '', $paragraph)))]);

            if (! $isKnown) {
                $kept[] = trim($paragraph);
            } elseif (end($kept) !== '[…]') {
                $kept[] = '[…]';
            }
        }

        return trim(implode("\n\n", array_filter($kept, fn (string $paragraph): bool => $paragraph !== '')));
    }

    /**
     * @return Collection<int, Email>
     */
    private function thread(Email $email): Collection
    {
        return Email::query()
            ->with('interpretation')
            ->where('mailbox_id', $email->mailbox_id)
            ->where('provider_thread_id', $email->provider_thread_id)
            ->whereKeyNot($email->id)
            ->get();
    }
}
