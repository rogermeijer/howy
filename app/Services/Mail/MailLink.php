<?php

namespace App\Services\Mail;

use App\Enums\MailLinkPurpose;

/**
 * What a link in one of Howy's mails stands for, as MailLinks issued it: the
 * account and record it opens, whom it was sent to, the language of the mail
 * it came in, and until when it works.
 */
final readonly class MailLink
{
    public function __construct(
        public int $accountId,
        public MailLinkPurpose $purpose,
        public int $recordId,
        public string $recipient,
        public string $locale,
        public ?int $expiresAt,
    ) {}

    public function expired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < now()->getTimestamp();
    }
}
