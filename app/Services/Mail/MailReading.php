<?php

namespace App\Services\Mail;

use App\Models\Email;

/**
 * A mail split into what Howy has not read yet and what it has.
 *
 * Only the new part is a source of knowledge. What is known is still useful
 * as context: "the date moved to 5 May" means nothing without the mail it
 * answers.
 *
 * @phpstan-import-type Quote from Email
 */
final readonly class MailReading
{
    /**
     * @param  string  $text  the mail's own text, with paragraphs read before replaced by […]
     * @param  int  $remaining  letters and digits left in it once those are taken out
     * @param  list<Quote>  $known  quoted or forwarded messages Howy has read before
     * @param  list<Quote>  $unseen  quoted or forwarded messages it has not
     * @param  list<int>  $knownFrom  the mails in which the known parts were first read
     */
    public function __construct(
        public string $text,
        public int $remaining,
        public int $knownParagraphs,
        public array $known,
        public array $unseen,
        public array $knownFrom,
    ) {}

    /**
     * Everything in it was read before: a forward without a note, the same
     * mail again. Reading it would only find what is already known.
     */
    public function nothingNew(): bool
    {
        return $this->remaining === 0
            && $this->unseen === []
            && ($this->knownParagraphs > 0 || $this->known !== []);
    }
}
