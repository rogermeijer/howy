<?php

namespace App\Enums;

/**
 * The role Howy takes in a mail, set by where the mailbox is on it.
 */
enum InterpretationMode: string
{
    /** The mailbox is in To: Howy is asked, and answers the sender. */
    case Addressed = 'addressed';

    /**
     * The mailbox is only copied (or blind-copied): Howy listens. It answers
     * nothing and writes to no one; it files what the people in the
     * conversation say.
     */
    case Copied = 'copied';

    public function label(): string
    {
        return match ($this) {
            self::Addressed => __('Addressed to the mailbox'),
            self::Copied => __('Mailbox in CC'),
        };
    }
}
