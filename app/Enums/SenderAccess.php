<?php

namespace App\Enums;

/**
 * What Howy may do with mail from one sender on a mailbox: learn from it
 * (propose its facts, file its attachments and history), answer it (reply,
 * copy them in on a reply), both, or neither. Set for the mailbox's own
 * domain, and per listed address or domain (MailboxSender) on top of that.
 */
enum SenderAccess: string
{
    case LearnAndAnswer = 'learn_answer';

    case Learn = 'learn';

    case Answer = 'answer';

    case Ignore = 'ignore';

    public function learns(): bool
    {
        return $this === self::LearnAndAnswer || $this === self::Learn;
    }

    public function answers(): bool
    {
        return $this === self::LearnAndAnswer || $this === self::Answer;
    }

    /**
     * Whether this allows at least everything the other allows.
     */
    public function covers(self $other): bool
    {
        return ($this->learns() || ! $other->learns()) && ($this->answers() || ! $other->answers());
    }

    /**
     * Every access that allows at least what this one does.
     *
     * @return list<self>
     */
    public function coveredBy(): array
    {
        return array_values(array_filter(self::cases(), fn (self $access): bool => $access->covers($this)));
    }

    public function label(): string
    {
        return match ($this) {
            self::LearnAndAnswer => __('Learn and answer'),
            self::Learn => __('Learn only'),
            self::Answer => __('Answer only'),
            self::Ignore => __('Ignore'),
        };
    }
}
